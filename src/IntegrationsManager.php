<?php

namespace Whilesmart\Integrations;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Whilesmart\Integrations\Contracts\OAuthProviderInterface;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Providers\GitHubProvider;
use Whilesmart\Integrations\Providers\GoogleCalendarProvider;
use Whilesmart\Integrations\Providers\GoogleDriveProvider;
use Whilesmart\Integrations\Providers\MicrosoftCalendarProvider;
use Whilesmart\Integrations\Providers\MicrosoftOneDriveProvider;

class IntegrationsManager
{
    protected array $providers = [];

    public function __construct()
    {
        $this->registerDefaultProviders();
    }

    /**
     * Get authorization URL for a provider.
     */
    public function getAuthorizationUrl(string $provider, string $state, array $scopes = []): string
    {
        $providerInstance = $this->getProvider($provider);

        if (! $providerInstance) {
            throw new Exception("Provider '{$provider}' not found.");
        }

        return $providerInstance->getAuthorizationUrl($state, $scopes);
    }

    /**
     * Handle OAuth callback and create integration.
     */
    public function handleCallback(string $provider, string $code, string $state, Authenticatable $user, ?string $workspaceId = null): Integration
    {
        $providerInstance = $this->getProvider($provider);

        if (! $providerInstance) {
            throw new Exception("Provider '{$provider}' not found.");
        }

        // Exchange code for token
        $tokenData = $providerInstance->exchangeCodeForToken($code, $state);

        // Get user info from provider
        $userInfo = $providerInstance->getUserInfo($tokenData['access_token']);

        $ownerType = $workspaceId ? config('integrations.workspace_model') : get_class($user);
        $ownerId = $workspaceId ?: $user->getAuthIdentifier();

        // Check if integration already exists
        $integration = Integration::forOwner($ownerType, $ownerId)
            ->where('provider', $provider)
            ->where('provider_user_id', $userInfo['id'])
            ->first();

        if ($integration) {
            // Update existing integration
            $integration->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                'provider_username' => $userInfo['username'] ?? $userInfo['name'],
                'provider_email' => $userInfo['email'],
                'mode' => Integration::MODE_MANAGED_LOCALLY,
                'status' => Integration::STATUS_CONNECTED,
                'is_active' => true,
                'last_synced_at' => now(),
            ]);
        } else {
            // Create new integration
            $integration = Integration::create([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'connected_by_type' => get_class($user),
                'connected_by_id' => $user->getAuthIdentifier(),
                // @phpstan-ignore-next-line
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $userInfo['id'],
                'provider_username' => $userInfo['username'] ?? $userInfo['name'],
                'provider_email' => $userInfo['email'],
                'mode' => Integration::MODE_MANAGED_LOCALLY,
                'status' => Integration::STATUS_CONNECTED,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
                'scopes' => $this->getProviderConfig($provider)['scopes'] ?? [],
                'is_active' => true,
                'last_synced_at' => now(),
                'metadata' => $userInfo,
            ]);
        }

        return $integration;
    }

    public function upsertExternalVaultIntegration(
        string $vaultProvider,
        string $provider,
        string $providerConfigKey,
        string $connectionId,
        string $ownerType,
        int|string $ownerId,
        ?Authenticatable $connectedBy = null,
        array $metadata = [],
        string $status = Integration::STATUS_CONNECTED
    ): Integration {
        $attributes = [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'vault_provider' => $vaultProvider,
            'vault_connection_id' => $connectionId,
        ];

        $values = [
            'provider' => $provider,
            'provider_user_id' => $metadata['provider_user_id'] ?? null,
            'provider_username' => $metadata['provider_username'] ?? null,
            'provider_email' => $metadata['provider_email'] ?? null,
            'mode' => Integration::MODE_EXTERNAL_VAULT,
            'status' => $status,
            'vault_provider_config_key' => $providerConfigKey,
            'is_active' => $status !== Integration::STATUS_DISCONNECTED,
            'last_synced_at' => now(),
            'metadata' => $metadata,
        ];

        if ($connectedBy) {
            $values['connected_by_type'] = get_class($connectedBy);
            $values['connected_by_id'] = $connectedBy->getAuthIdentifier();
            $values['user_id'] = $connectedBy->getAuthIdentifier();
        }

        return Integration::updateOrCreate($attributes, $values);
    }

    /**
     * Refresh an expired token.
     */
    public function refreshToken(Integration $integration): bool
    {
        if (! $integration->refresh_token) {
            return false;
        }

        $providerInstance = $this->getProvider($integration->provider);

        if (! $providerInstance || ! $providerInstance->supportsTokenRefresh()) {
            return false;
        }

        try {
            $tokenData = $providerInstance->refreshToken($integration->refresh_token);

            $integration->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $integration->refresh_token,
                'token_expires_at' => $tokenData['expires_in'] ? now()->addSeconds($tokenData['expires_in']) : null,
            ]);

            if (config('integrations.logging.log_token_refresh', true)) {
                Log::channel(config('integrations.logging.channel'))
                    ->info('Token refreshed successfully', [
                        'integration_id' => $integration->id,
                        'provider' => $integration->provider,
                    ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::channel(config('integrations.logging.channel'))
                ->error('Token refresh failed', [
                    'integration_id' => $integration->id,
                    'provider' => $integration->provider,
                    'error' => $e->getMessage(),
                ]);

            return false;
        }
    }

    /**
     * Make API call using integration.
     */
    public function makeApiCall(Integration $integration, string $method, string $endpoint, array $data = []): array
    {
        if ($integration->isExternalVault()) {
            throw new Exception('External vault integrations must be accessed through their vault provider client.');
        }

        // Auto-refresh token if needed
        if (config('integrations.storage.auto_refresh_tokens', true) && $integration->isTokenExpired()) {
            if (! $this->refreshToken($integration)) {
                throw new Exception('Token expired and refresh failed');
            }
            $integration->refresh(); // Reload from database
        }

        $providerInstance = $this->getProvider($integration->provider);

        if (! $providerInstance) {
            throw new Exception("Provider '{$integration->provider}' not found.");
        }

        return $providerInstance->makeApiCall($integration->access_token, $method, $endpoint, $data);
    }

    /**
     * Revoke token.
     */
    public function revokeToken(string $provider, string $accessToken): bool
    {
        $providerInstance = $this->getProvider($provider);

        if (! $providerInstance) {
            return false;
        }

        try {
            return $providerInstance->revokeToken($accessToken);
        } catch (\Exception $e) {
            Log::channel(config('integrations.logging.channel'))
                ->warning('Token revocation failed', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);

            return false;
        }
    }

    /**
     * Check if provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool
    {
        $config = $this->getProviderConfig($provider);

        return $config['enabled'] ?? false;
    }

    /**
     * Register a custom provider.
     */
    public function extend(string $name, \Closure|OAuthProviderInterface $provider): void
    {
        if ($provider instanceof \Closure) {
            $this->providers[$name] = $provider;
        } else {
            $this->providers[$name] = fn () => $provider;
        }
    }

    protected function registerDefaultProviders(): void
    {
        $this->providers['github'] = fn () => new GitHubProvider(
            $this->getProviderConfig('github'),
            'github'
        );

        $this->providers['google_calendar'] = fn () => new GoogleCalendarProvider(
            $this->getProviderConfig('google_calendar'),
            'google_calendar'
        );

        $this->providers['google_drive'] = fn () => new GoogleDriveProvider(
            $this->getProviderConfig('google_drive'),
            'google_drive'
        );

        $this->providers['microsoft_calendar'] = fn () => new MicrosoftCalendarProvider(
            $this->getProviderConfig('microsoft_calendar'),
            'microsoft_calendar'
        );

        $this->providers['microsoft_onedrive'] = fn () => new MicrosoftOneDriveProvider(
            $this->getProviderConfig('microsoft_onedrive'),
            'microsoft_onedrive'
        );
    }

    protected function getProvider(string $provider): ?OAuthProviderInterface
    {
        if (! isset($this->providers[$provider])) {
            return null;
        }

        return $this->providers[$provider]();
    }

    protected function getProviderConfig(string $provider): array
    {
        return config("integrations.oauth_providers.{$provider}", []);
    }
}
