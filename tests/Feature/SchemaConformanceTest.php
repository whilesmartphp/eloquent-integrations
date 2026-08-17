<?php

namespace Whilesmart\Integrations\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Tests\TestCase;
use Whilesmart\SchemaConformance\Testing\SchemaConformanceAssertions;

/**
 * Keeps the integrations table, the model that reads it, and the declared spec
 * in agreement. A column added to the model but never migrated, or migrated in
 * one environment and not another, fails here rather than on the first write.
 */
class SchemaConformanceTest extends TestCase
{
    use RefreshDatabase;
    use SchemaConformanceAssertions;

    protected function defineEnvironment($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('schema.tables.integrations', [
            'columns' => [
                'owner_type' => ['type' => 'string', 'nullable' => true],
                'owner_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
                'connected_by_type' => ['type' => 'string', 'nullable' => true],
                'connected_by_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
                'user_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
                'provider' => ['type' => 'string'],
                'provider_user_id' => ['type' => 'string', 'nullable' => true],
                'provider_username' => ['type' => 'string', 'nullable' => true],
                'provider_email' => ['type' => 'string', 'nullable' => true],
                'mode' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'credential_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
                'access_token' => ['type' => 'text', 'nullable' => true],
                'refresh_token' => ['type' => 'text', 'nullable' => true],
                'token_expires_at' => ['type' => 'timestamp', 'nullable' => true],
                'vault_provider' => ['type' => 'string', 'nullable' => true],
                'vault_connection_id' => ['type' => 'string', 'nullable' => true],
                'vault_provider_config_key' => ['type' => 'string', 'nullable' => true],
                'scopes' => ['type' => 'json', 'nullable' => true],
                'context_type' => ['type' => 'string', 'nullable' => true],
                'context_id' => ['type' => 'unsignedBigInteger', 'nullable' => true],
                'settings' => ['type' => 'json', 'nullable' => true],
                'metadata' => ['type' => 'json', 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'last_synced_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'indexes' => [
                ['columns' => ['vault_provider', 'vault_connection_id']],
                ['columns' => ['context_type', 'context_id']],
                ['columns' => ['provider']],
                ['columns' => ['status']],
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    #[Test]
    public function the_live_table_matches_the_declared_spec(): void
    {
        $this->assertSchemaConformant();
    }

    #[Test]
    public function every_column_an_alter_migration_adds_is_declared(): void
    {
        $this->assertAlteredColumnsDeclared(__DIR__.'/../../database/migrations');
    }

    #[Test]
    public function every_attribute_the_model_writes_has_a_column(): void
    {
        $this->assertModelAttributesHaveColumns([Integration::class]);
    }
}
