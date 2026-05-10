<?php

namespace Whilesmart\Integrations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * @property  string $provider
 * @property  string $mode
 * @property  string $vault_provider
 * @property  string $status
 * @property  integer $vault_connection_id
 * @property  integer $owner_id
 * @property  integer $connected_by_id
 * @property  string $vault_provider_config_key
 * @property  string $provider_email
 * @property  string $connected_by_type
 * @property  string $owner_type
 * @property  string $provider_username
 * @property  bool $is_active
 * @property  ?Carbon $token_expires_at
 * @property  ?Carbon $created_at
 * @property  ?Carbon $last_synced_at
 * @property array $settings
 * @property array $scopes
 * @property array $metadata
*/
class Integration extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const MODE_MANAGED_LOCALLY = 'managed_locally';

    public const MODE_EXTERNAL_VAULT = 'external_vault';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'connected_by_type',
        'connected_by_id',
        'user_id',
        'provider',
        'provider_user_id',
        'provider_username',
        'provider_email',
        'mode',
        'status',
        'credential_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'vault_provider',
        'vault_connection_id',
        'vault_provider_config_key',
        'scopes',
        'context_type',
        'context_id',
        'settings',
        'metadata',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('integrations.user_model', 'App\\Models\\User'));
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function connectedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'connected_by_type', 'connected_by_id');
    }

    /**
     * Get the decrypted access token.
     */
    public function getAccessTokenAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        if (config('integrations.storage.encrypt_tokens', true)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Set the encrypted access token.
     */
    public function setAccessTokenAttribute($value): void
    {
        if (! $value) {
            $this->attributes['access_token'] = null;

            return;
        }

        if (config('integrations.storage.encrypt_tokens', true)) {
            $this->attributes['access_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['access_token'] = $value;
        }
    }

    /**
     * Get the decrypted refresh token.
     */
    public function getRefreshTokenAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        if (config('integrations.storage.encrypt_tokens', true)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Set the encrypted refresh token.
     */
    public function setRefreshTokenAttribute($value): void
    {
        if (! $value) {
            $this->attributes['refresh_token'] = null;

            return;
        }

        if (config('integrations.storage.encrypt_tokens', true)) {
            $this->attributes['refresh_token'] = Crypt::encryptString($value);
        } else {
            $this->attributes['refresh_token'] = $value;
        }
    }

    /**
     * Check if the access token is expired or about to expire.
     */
    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        $buffer = config('integrations.storage.token_expiry_buffer', 300);

        return $this->token_expires_at->subSeconds($buffer)->isPast();
    }

    /**
     * Check if the integration is active and has a valid token.
     */
    public function isValid(): bool
    {
        if (! $this->is_active || $this->status !== self::STATUS_CONNECTED) {
            return false;
        }

        if ($this->isExternalVault()) {
            return filled($this->vault_provider) && filled($this->vault_connection_id);
        }

        return filled($this->access_token) && ! $this->isTokenExpired();
    }

    public function isManagedLocally(): bool
    {
        return $this->mode === self::MODE_MANAGED_LOCALLY;
    }

    public function isExternalVault(): bool
    {
        return $this->mode === self::MODE_EXTERNAL_VAULT;
    }

    /**
     * Get the provider configuration.
     */
    public function getProviderConfig(): array
    {
        return config("integrations.oauth_providers.{$this->provider}", []);
    }

    /**
     * Scope query to active integrations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to specific provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope query to specific workspace.
     */
    public function scopeForOwner($query, string $ownerType, int|string $ownerId)
    {
        return $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
    }

    public function scopeInWorkspace($query, int|string $workspaceId)
    {
        return $query->forOwner(config('integrations.workspace_model'), $workspaceId);
    }

    public function scopeExternalVault($query, ?string $vaultProvider = null)
    {
        $query->where('mode', self::MODE_EXTERNAL_VAULT);

        if ($vaultProvider) {
            $query->where('vault_provider', $vaultProvider);
        }

        return $query;
    }
}
