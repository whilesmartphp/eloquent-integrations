<?php

namespace Whilesmart\Integrations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Integrations\Http\Controllers\IntegrationController;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Tests\TestCase;

class NangoConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('integrations.external_vaults.nango', [
            'enabled' => true,
            'base_url' => 'https://vault.test',
            'secret_key' => 'secret',
            'connection_id_prefix' => 'test',
        ]);

        config()->set('integrations.nango_providers.github', [
            'enabled' => true,
            'name' => 'GitHub',
            'provider_config_key' => 'github-app-oauth',
        ]);
    }

    protected function defineRoutes($router): void
    {
        $router->post('/integrations/nango/session', [IntegrationController::class, 'nangoConnectSession']);
        $router->post('/integrations/nango/connection', [IntegrationController::class, 'nangoConnection']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    #[Test]
    public function the_configured_provider_key_is_used_when_the_caller_does_not_send_one(): void
    {
        Http::fake([
            'vault.test/connect/sessions' => Http::response(['data' => ['token' => 'session-token']]),
        ]);

        $response = $this->actingAs($this->user())
            ->postJson('/integrations/nango/session', ['provider' => 'github']);

        $response->assertOk()
            ->assertJsonPath('data.provider_config_key', 'github-app-oauth');
    }

    #[Test]
    public function a_provider_missing_from_the_vault_is_reported_in_plain_language(): void
    {
        Http::fake([
            'vault.test/connect/sessions' => Http::response([
                'error' => ['code' => 'invalid_body', 'errors' => [['message' => 'Integration does not exist']]],
            ], 400),
        ]);

        $response = $this->actingAs($this->user())
            ->postJson('/integrations/nango/session', ['provider' => 'github']);

        $response->assertStatus(422)
            ->assertJsonPath('reason', 'provider_not_configured')
            ->assertJsonPath('message', 'GitHub is not available yet. It has not been set up in the connection vault.');
    }

    #[Test]
    public function any_other_vault_failure_is_reported_without_leaking_its_internals(): void
    {
        Http::fake([
            'vault.test/connect/sessions' => Http::response(['error' => ['code' => 'server_error']], 500),
        ]);

        $response = $this->actingAs($this->user())
            ->postJson('/integrations/nango/session', ['provider' => 'github']);

        $response->assertStatus(422)->assertJsonPath('reason', 'vault_error');
        $this->assertStringNotContainsString('server_error', (string) $response->getContent());
    }

    #[Test]
    public function a_completed_connection_is_recorded_once_the_vault_confirms_it(): void
    {
        Http::fake([
            'vault.test/connection/*' => Http::response(['connection_id' => 'conn-1', 'provider' => 'github']),
        ]);

        $user = $this->user();

        $response = $this->actingAs($user)->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => 'conn-1',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', Integration::STATUS_CONNECTED);

        $this->assertDatabaseHas('integrations', [
            'provider' => 'github',
            'vault_connection_id' => 'conn-1',
            'vault_provider_config_key' => 'github-app-oauth',
        ]);
    }

    #[Test]
    public function a_connection_the_vault_does_not_know_is_refused(): void
    {
        Http::fake([
            'vault.test/connection/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $response = $this->actingAs($this->user())->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => 'invented',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('integrations', 0);
    }

    private function user(): object
    {
        $model = new class () extends \Illuminate\Foundation\Auth\User {
            protected $table = 'users';

            protected $guarded = [];
        };

        \Illuminate\Support\Facades\Schema::hasTable('users') || \Illuminate\Support\Facades\Schema::create('users', function ($table) {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        return $model::create(['email' => 'connector@example.test']);
    }
}
