<?php

namespace Whilesmart\Integrations\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Integrations\Http\Controllers\IntegrationController;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Services\NangoClient;
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
        $user = $this->user();
        $connectionId = app(NangoClient::class)->connectionId(get_class($user), $user->getAuthIdentifier(), 'github-app-oauth');

        Http::fake([
            'vault.test/connection/*' => Http::response(['connection_id' => $connectionId, 'provider' => 'github']),
        ]);

        $response = $this->actingAs($user)->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => $connectionId,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', Integration::STATUS_CONNECTED);

        $this->assertDatabaseHas('integrations', [
            'provider' => 'github',
            'vault_connection_id' => $connectionId,
            'vault_provider_config_key' => 'github-app-oauth',
        ]);
    }

    #[Test]
    public function a_connection_id_that_is_not_the_callers_is_refused(): void
    {
        $response = $this->actingAs($this->user())->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => 'someone-elses-connection',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('integrations', 0);
    }

    #[Test]
    public function a_connection_the_vault_does_not_know_is_refused(): void
    {
        $user = $this->user();
        $connectionId = app(NangoClient::class)->connectionId(get_class($user), $user->getAuthIdentifier(), 'github-app-oauth');

        Http::fake([
            'vault.test/connection/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $response = $this->actingAs($user)->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => $connectionId,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('integrations', 0);
    }

    #[Test]
    public function a_vault_failure_while_recording_is_reported_like_the_session(): void
    {
        $user = $this->user();
        $connectionId = app(NangoClient::class)->connectionId(get_class($user), $user->getAuthIdentifier(), 'github-app-oauth');

        Http::fake([
            'vault.test/connection/*' => Http::response(['error' => ['code' => 'server_error']], 500),
        ]);

        $response = $this->actingAs($user)->postJson('/integrations/nango/connection', [
            'provider' => 'github',
            'provider_config_key' => 'github-app-oauth',
            'connection_id' => $connectionId,
        ]);

        $response->assertStatus(422)->assertJsonPath('reason', 'vault_error');
        $this->assertDatabaseCount('integrations', 0);
    }

    private function user(): object
    {
        $model = new class extends User
        {
            protected $table = 'users';

            protected $guarded = [];
        };

        Schema::hasTable('users') || Schema::create('users', function ($table) {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        return $model::create(['email' => 'connector@example.test']);
    }
}
