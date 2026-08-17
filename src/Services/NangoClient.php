<?php

namespace Whilesmart\Integrations\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class NangoClient
{
    public function createConnectSession(array $tags = [], array $allowedIntegrations = [], array $defaults = [], array $overrides = []): array
    {
        $payload = array_filter(
            [
            'tags' => $tags,
            'allowed_integrations' => $allowedIntegrations,
            'integrations_config_defaults' => $defaults,
            'overrides' => $overrides,
            ],
            // @phpstan-ignore-next-line
            fn ($value) => $value !== [] && $value !== null
        );

        return $this->request()
            ->post('/connect/sessions', $payload)
            ->throw()
            ->json('data', []);
    }

    /**
     * The connection as the vault holds it, or null when it holds no such
     * connection. Used to confirm a connection a client claims to have made.
     */
    public function connection(string $connectionId, string $providerConfigKey): ?array
    {
        $response = $this->request()
            ->get('/connection/' . urlencode($connectionId), [
                'provider_config_key' => $providerConfigKey,
            ]);

        return $response->successful() ? $response->json() : null;
    }

    public function verifyWebhook(string $rawPayload, ?string $signature): bool
    {
        $secret = config('integrations.external_vaults.nango.webhook_secret');

        if (! $secret || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signature);
    }

    public function connectionId(string $ownerType, int|string $ownerId, string $provider): string
    {
        $prefix = str(config('integrations.external_vaults.nango.connection_id_prefix', 'app'))
            ->lower()
            ->replaceMatches('/[^a-z0-9_\\-]+/', '_')
            ->trim('_')
            ->toString();

        $owner = class_basename($ownerType);

        return sprintf('%s_%s_%s_%s', $prefix, str($owner)->snake(), $ownerId, $provider);
    }

    protected function request(): PendingRequest
    {
        $secretKey = config('integrations.external_vaults.nango.secret_key');

        if (! $secretKey) {
            throw new InvalidArgumentException('NANGO_SECRET_KEY is not configured.');
        }

        $baseUrl = rtrim((string) config('integrations.external_vaults.nango.base_url'), '/');

        if (! $baseUrl) {
            throw new RuntimeException('NANGO_BASE_URL is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($secretKey);
    }
}
