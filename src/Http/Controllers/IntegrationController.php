<?php

namespace Whilesmart\Integrations\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Whilesmart\Integrations\IntegrationsManager;
use Whilesmart\Integrations\Models\Integration;
use Whilesmart\Integrations\Services\NangoClient;

class IntegrationController extends Controller
{
    public function __construct(protected IntegrationsManager $manager)
    {
    }

    public function index(Request $request, ?string $workspaceId = null): JsonResponse
    {
        $query = Integration::query()->active();

        if ($workspaceId && config('integrations.workspace_scoped', true)) {
            if (! $this->canAccessWorkspace($workspaceId)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $query->inWorkspace($workspaceId);
        } else {
            $user = $request->user();
            $query->where(function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('owner_type', get_class($user))
                        ->where('owner_id', $user->getAuthIdentifier());
                })->orWhere(function ($query) use ($user) {
                    $query->where('connected_by_type', get_class($user))
                        ->where('connected_by_id', $user->getAuthIdentifier());
                })->orWhere('user_id', $user->getAuthIdentifier());
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()->map(fn (Integration $integration) => $this->resource($integration)),
        ]);
    }

    public function authorize(Request $request, string $provider, ?string $workspaceId = null): JsonResponse
    {
        try {
            if ($workspaceId && ! $this->canAccessWorkspace($workspaceId)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if (! $this->manager->isProviderEnabled($provider)) {
                return response()->json([
                    'success' => false,
                    'message' => "Provider '{$provider}' is not enabled or configured.",
                ], 400);
            }

            $state = Str::random(40);
            $request->session()->put('oauth_state', $state);
            $request->session()->put('oauth_provider', $provider);

            if ($workspaceId) {
                $request->session()->put('oauth_workspace_id', $workspaceId);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'authorization_url' => $this->manager->getAuthorizationUrl($provider, $state),
                    'state' => $state,
                ],
            ]);
        } catch (\Exception $e) {
            Log::channel(config('integrations.logging.channel'))
                ->error('OAuth authorization failed', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize OAuth flow.',
            ], 500);
        }
    }

    /**
     * Record a connection the caller has just completed in the browser. The
     * connection is confirmed against the vault before anything is stored, so
     * a caller cannot claim one it never made.
     */
    public function nangoConnection(Request $request, NangoClient $nango, ?string $workspaceId = null): JsonResponse
    {
        if (! config('integrations.external_vaults.nango.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Nango is not enabled.',
            ], 400);
        }

        if ($workspaceId && ! $this->canAccessWorkspace($workspaceId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'provider_config_key' => ['required', 'string', 'max:100'],
            'connection_id' => ['required', 'string', 'max:255'],
        ]);

        $connection = $nango->connection($validated['connection_id'], $validated['provider_config_key']);

        if ($connection === null) {
            return response()->json([
                'success' => false,
                'message' => 'That connection does not exist.',
            ], 404);
        }

        $user = $request->user();
        [$ownerType, $ownerId] = $workspaceId
            ? [config('integrations.workspace_model'), $workspaceId]
            : [get_class($user), $user->getAuthIdentifier()];

        $integration = $this->manager->upsertExternalVaultIntegration(
            'nango',
            $validated['provider'],
            $validated['provider_config_key'],
            $validated['connection_id'],
            $ownerType,
            $ownerId,
            $user,
            ['nango' => $connection],
        );

        return response()->json([
            'success' => true,
            'data' => $this->resource($integration),
        ], 201);
    }

    public function nangoConnectSession(Request $request, NangoClient $nango, ?string $workspaceId = null): JsonResponse
    {
        if (! config('integrations.external_vaults.nango.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Nango is not enabled.',
            ], 400);
        }

        if ($workspaceId && ! $this->canAccessWorkspace($workspaceId)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:100'],
            'provider_config_key' => ['nullable', 'string', 'max:100'],
            'allowed_integrations' => ['nullable', 'array'],
            'allowed_integrations.*' => ['string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'integrations_config_defaults' => ['nullable', 'array'],
            'overrides' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        [$ownerType, $ownerId] = $workspaceId
            ? [config('integrations.workspace_model'), $workspaceId]
            : [get_class($user), $user->getAuthIdentifier()];

        $providerConfigKey = $validated['provider_config_key']
            ?? config('integrations.nango_providers.' . $validated['provider'] . '.provider_config_key')
            ?? $validated['provider'];
        $connectionId = $nango->connectionId($ownerType, $ownerId, $providerConfigKey);

        $tags = array_merge($validated['tags'] ?? [], [
            'owner_type' => $ownerType,
            'owner_id' => (string) $ownerId,
            'connected_by_type' => get_class($user),
            'connected_by_id' => (string) $user->getAuthIdentifier(),
            'connected_by_email' => (string) ($user->email ?? ''),
            'provider' => $validated['provider'],
            'provider_config_key' => $providerConfigKey,
        ]);

        try {
            $session = $nango->createConnectSession(
                $tags,
                $validated['allowed_integrations'] ?? [$providerConfigKey],
                $validated['integrations_config_defaults'] ?? [],
                $validated['overrides'] ?? [],
            );
        } catch (RequestException $e) {
            return $this->vaultFailure($e, $validated['provider'], $providerConfigKey);
        }

        return response()->json([
            'success' => true,
            'data' => [
                ...$session,
                'connection_id' => $connectionId,
                'provider_config_key' => $providerConfigKey,
            ],
        ]);
    }

    public function callback(Request $request): JsonResponse
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');

            if (! $code || ! $state) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing authorization code or state.',
                ], 400);
            }

            if ($state !== $request->session()->get('oauth_state')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid state parameter.',
                ], 400);
            }

            $provider = $request->session()->get('oauth_provider');
            $workspaceId = $request->session()->get('oauth_workspace_id');

            if (! $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OAuth session.',
                ], 400);
            }

            $integration = $this->manager->handleCallback(
                $provider,
                $code,
                $state,
                $request->user(),
                $workspaceId
            );

            $request->session()->forget(['oauth_state', 'oauth_provider', 'oauth_workspace_id']);

            return response()->json([
                'success' => true,
                'data' => $this->resource($integration),
            ]);
        } catch (\Exception $e) {
            Log::channel(config('integrations.logging.channel'))
                ->error('OAuth callback failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'OAuth integration failed.',
            ], 500);
        }
    }

    public function show(?string $workspaceId = null, ?int $integrationId = null): JsonResponse
    {
        $integration = $this->findIntegration($workspaceId, $integrationId);

        if (! $integration) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->resource($integration, true),
        ]);
    }

    public function update(Request $request, ?string $workspaceId = null, ?int $integrationId = null): JsonResponse
    {
        $integration = $this->findIntegration($workspaceId, $integrationId);

        if (! $integration) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not found.',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([
                Integration::STATUS_PENDING,
                Integration::STATUS_CONNECTED,
                Integration::STATUS_FAILED,
                Integration::STATUS_DISCONNECTED,
            ])],
            'settings' => ['sometimes', 'nullable', 'array'],
        ]);

        $integration->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->resource($integration->refresh(), true),
        ]);
    }

    public function destroy(?string $workspaceId = null, ?int $integrationId = null): JsonResponse
    {
        $integration = $this->findIntegration($workspaceId, $integrationId);

        if (! $integration) {
            return response()->json([
                'success' => false,
                'message' => 'Integration not found.',
            ], 404);
        }

        try {
            if ($integration->isManagedLocally() && $integration->access_token) {
                $this->manager->revokeToken($integration->provider, $integration->access_token);
            }
        } catch (\Exception $e) {
            Log::channel(config('integrations.logging.channel'))
                ->warning('Failed to revoke token during integration deletion', [
                    'integration_id' => $integration->id,
                    'provider' => $integration->provider,
                    'error' => $e->getMessage(),
                ]);
        }

        $integration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Integration deleted successfully.',
        ]);
    }

    public function providers(): JsonResponse
    {
        $providers = [];

        foreach (config('integrations.oauth_providers', []) as $key => $config) {
            if ($config['enabled']) {
                $providers[$key] = [
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'scopes' => $config['scopes'],
                    'mode' => Integration::MODE_MANAGED_LOCALLY,
                ];
            }
        }

        foreach (config('integrations.nango_providers', []) as $key => $config) {
            if ($config['enabled'] ?? true) {
                $providers[$key] = [
                    'name' => $config['name'] ?? ucfirst(str_replace('_', ' ', $key)),
                    'scopes' => $config['scopes'] ?? [],
                    'mode' => Integration::MODE_EXTERNAL_VAULT,
                    'vault_provider' => 'nango',
                    'provider_config_key' => $config['provider_config_key'] ?? $key,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $providers,
        ]);
    }

    public function nangoWebhook(Request $request, NangoClient $nango): JsonResponse
    {
        if (! $nango->verifyWebhook($request->getContent(), $request->header('X-Nango-Hmac-Sha256'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $payload = $request->all();
        $type = $payload['type'] ?? null;

        if ($type === 'auth') {
            $this->handleNangoAuthWebhook($payload);
        } elseif ($type === 'sync') {
            $this->handleNangoSyncWebhook($payload);
        } elseif ($type === 'forward') {
            $this->handleNangoForwardWebhook($payload);
        }

        return response()->json(['success' => true]);
    }

    protected function handleNangoAuthWebhook(array $payload): void
    {
        $tags = $payload['tags'] ?? [];
        $ownerType = $tags['owner_type'] ?? null;
        $ownerId = $tags['owner_id'] ?? null;
        $connectionId = $payload['connectionId'] ?? null;
        $providerConfigKey = $payload['providerConfigKey'] ?? null;

        if (! $ownerType || ! $ownerId || ! $connectionId || ! $providerConfigKey) {
            Log::warning('Nango auth webhook missing owner or connection identifiers', ['payload' => $payload]);

            return;
        }

        $status = ($payload['success'] ?? false)
            ? Integration::STATUS_CONNECTED
            : Integration::STATUS_FAILED;

        $connectedBy = $this->connectedByFromTags($tags);

        $this->manager->upsertExternalVaultIntegration(
            'nango',
            $payload['provider'] ?? $providerConfigKey,
            $providerConfigKey,
            $connectionId,
            $ownerType,
            $ownerId,
            $connectedBy,
            ['nango' => $payload],
            $status
        );
    }

    protected function handleNangoSyncWebhook(array $payload): void
    {
        $integration = Integration::externalVault('nango')
            ->where('vault_connection_id', $payload['connectionId'] ?? null)
            ->where('vault_provider_config_key', $payload['providerConfigKey'] ?? null)
            ->first();

        if (! $integration) {
            return;
        }

        $metadata = $integration->metadata ?? [];
        $metadata['last_nango_sync'] = $payload;
        $metadata['sync'] = [
            'modified_after' => $payload['modifiedAfter'] ?? data_get($metadata, 'sync.modified_after'),
            'sync_name' => $payload['syncName'] ?? null,
            'model' => $payload['model'] ?? null,
        ];

        $integration->update([
            'status' => ($payload['success'] ?? false) ? Integration::STATUS_CONNECTED : Integration::STATUS_FAILED,
            'last_synced_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    protected function handleNangoForwardWebhook(array $payload): void
    {
        $integration = Integration::externalVault('nango')
            ->where('vault_connection_id', $payload['connectionId'] ?? null)
            ->where('vault_provider_config_key', $payload['providerConfigKey'] ?? null)
            ->first();

        if (! $integration) {
            return;
        }

        $metadata = $integration->metadata ?? [];
        $metadata['last_forwarded_webhook'] = $payload;

        $integration->update(['metadata' => $metadata]);
    }

    protected function findIntegration(?string $workspaceId, ?int $integrationId): ?Integration
    {
        if ($integrationId === null) {
            $integrationId = (int) $workspaceId;
            $workspaceId = null;
        }

        $query = Integration::where('id', $integrationId);

        if ($workspaceId) {
            if (! $this->canAccessWorkspace($workspaceId)) {
                return null;
            }

            $query->inWorkspace($workspaceId);
        } else {
            $user = auth()->user();
            $query->where(function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('owner_type', get_class($user))
                        ->where('owner_id', $user->getAuthIdentifier());
                })->orWhere(function ($query) use ($user) {
                    $query->where('connected_by_type', get_class($user))
                        ->where('connected_by_id', $user->getAuthIdentifier());
                })->orWhere('user_id', $user->getAuthIdentifier());
            });
        }

        return $query->first();
    }

    protected function resource(Integration $integration, bool $includeDetails = false): array
    {
        $data = [
            'id' => $integration->id,
            'owner_type' => $integration->owner_type,
            'owner_id' => $integration->owner_id,
            'connected_by_type' => $integration->connected_by_type,
            'connected_by_id' => $integration->connected_by_id,
            'provider' => $integration->provider,
            'provider_username' => $integration->provider_username,
            'provider_email' => $integration->provider_email,
            'mode' => $integration->mode,
            'status' => $integration->status,
            'vault_provider' => $integration->vault_provider,
            'vault_connection_id' => $integration->vault_connection_id,
            'vault_provider_config_key' => $integration->vault_provider_config_key,
            'is_active' => $integration->is_active,
            'last_synced_at' => $integration->last_synced_at?->toISOString(),
            'scopes' => $integration->scopes,
        ];

        if ($includeDetails) {
            $data['settings'] = $integration->settings;
            $data['metadata'] = $integration->metadata;
            $data['created_at'] = $integration->created_at?->toISOString();
        }

        return $data;
    }

    protected function canAccessWorkspace(string $workspaceId): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $workspaceModel = config('integrations.workspace_model');
        // @phpstan-ignore-next-line
        return $user->hasRole('workspace-member', $workspaceModel, $workspaceId)
            // @phpstan-ignore-next-line
            || $user->hasRole('workspace-owner', $workspaceModel, $workspaceId)
            // @phpstan-ignore-next-line
            || $user->hasRole('workspace-admin', $workspaceModel, $workspaceId);
    }

    protected function connectedByFromTags(array $tags): ?Authenticatable
    {
        $type = $tags['connected_by_type'] ?? null;
        $connected_by_id = $tags['connected_by_id'] ?? null;

        if (! $type || ! $connected_by_id || ! class_exists($type)) {
            return null;
        }

        $model = new $type();

        return $model->newQuery()->find($connected_by_id);
    }

    /**
     * The vault answers with its own error shapes, which mean nothing to the
     * person who pressed Connect. The commonest by far is a provider that was
     * never set up in the vault, which reads as a bare validation failure.
     */
    protected function vaultFailure(RequestException $e, string $provider, string $providerConfigKey): JsonResponse
    {
        $body = (string) $e->response->body();
        $name = config('integrations.nango_providers.' . $provider . '.name', Str::headline($provider));

        Log::warning('Nango rejected a connect session', [
            'provider' => $provider,
            'provider_config_key' => $providerConfigKey,
            'status' => $e->response->status(),
            'body' => $body,
        ]);

        if (str_contains($body, 'Integration does not exist')) {
            return response()->json([
                'success' => false,
                'message' => $name . ' is not available yet. It has not been set up in the connection vault.',
                'reason' => 'provider_not_configured',
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'Could not start the connection to ' . $name . '. Please try again.',
            'reason' => 'vault_error',
        ], 422);
    }
}
