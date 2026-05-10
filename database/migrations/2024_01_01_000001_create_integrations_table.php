<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->nullableMorphs('connected_by');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('provider_user_id')->nullable();
            $table->string('provider_username')->nullable();
            $table->string('provider_email')->nullable();
            $table->string('mode')->default('managed_locally');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('vault_provider')->nullable();
            $table->string('vault_connection_id')->nullable();
            $table->string('vault_provider_config_key')->nullable();
            $table->json('scopes')->nullable();
            $table->string('context_type')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['provider']);
            $table->index(['mode']);
            $table->index(['status']);
            $table->index(['vault_provider', 'vault_connection_id']);
            $table->index(['context_type', 'context_id']);
            $table->index(['is_active']);
            $table->index(['provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
