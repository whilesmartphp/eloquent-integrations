<?php

namespace Whilesmart\Integrations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Integrations\Events\ProviderWebhookForwarded;
use Whilesmart\Integrations\Http\Controllers\IntegrationController;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Tests\TestCase;

class ForwardedWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('integrations.external_vaults.nango', [
            'enabled' => true,
            'base_url' => 'https://vault.test',
            'secret_key' => 'secret',
            'webhook_secret' => 'test-secret',
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->post('/integrations/nango/webhook', [IntegrationController::class, 'nangoWebhook']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    #[Test]
    public function a_forwarded_webhook_is_handed_to_the_host(): void
    {
        Event::fake([ProviderWebhookForwarded::class]);

        $integration = $this->connectedIntegration();

        $response = $this->deliver([
            'type' => 'forward',
            'from' => 'github-app-oauth',
            'providerConfigKey' => 'github-app-oauth',
            'connectionId' => 'conn-1',
            'payload' => $this->githubPush(),
        ], ['X-GitHub-Event' => 'push']);

        $response->assertOk();

        Event::assertDispatched(ProviderWebhookForwarded::class, function (ProviderWebhookForwarded $event) use ($integration) {
            return $event->integration->is($integration)
                && $event->body['repository']['full_name'] === 'trailpad/ui'
                && $event->body['head_commit']['message'] === 'chore: Trigger a webhook delivery'
                && $event->headers['x-github-event'][0] === 'push';
        });
    }

    #[Test]
    public function a_webhook_for_an_unknown_connection_is_ignored(): void
    {
        Event::fake([ProviderWebhookForwarded::class]);

        $this->deliver([
            'type' => 'forward',
            'providerConfigKey' => 'github-app-oauth',
            'connectionId' => 'a-connection-we-never-stored',
            'payload' => $this->githubPush(),
        ])->assertOk();

        Event::assertNotDispatched(ProviderWebhookForwarded::class);
    }

    #[Test]
    public function the_delivered_envelope_is_kept_on_the_integration(): void
    {
        $integration = $this->connectedIntegration();

        $this->deliver([
            'type' => 'forward',
            'providerConfigKey' => 'github-app-oauth',
            'connectionId' => 'conn-1',
            'payload' => $this->githubPush(),
        ])->assertOk();

        $forwarded = $integration->refresh()->metadata['last_forwarded_webhook'];

        $this->assertSame('trailpad/ui', $forwarded['payload']['repository']['full_name']);
    }

    private function connectedIntegration(): Integration
    {
        return Integration::create([
            'provider' => 'github',
            'mode' => Integration::MODE_EXTERNAL_VAULT,
            'status' => Integration::STATUS_CONNECTED,
            'vault_provider' => 'nango',
            'vault_connection_id' => 'conn-1',
            'vault_provider_config_key' => 'github-app-oauth',
            'is_active' => true,
        ]);
    }

    /** A delivery GitHub actually made, captured from the App's deliveries API. */
    private function githubPush(): array
    {
        return json_decode(file_get_contents(__DIR__ . '/../Fixtures/github-push.json'), true);
    }

    /** Deliver a webhook the way the vault does: raw JSON, signed. */
    private function deliver(array $body, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($body);

        return $this->call(
            'POST',
            '/integrations/nango/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars(array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Nango-Hmac-Sha256' => hash_hmac('sha256', $json, 'test-secret'),
            ], $headers)),
            $json
        );
    }
}
