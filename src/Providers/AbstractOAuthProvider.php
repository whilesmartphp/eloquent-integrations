<?php

namespace Whilesmart\Integrations\Providers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Whilesmart\Integrations\Contracts\OAuthProviderInterface;

abstract class AbstractOAuthProvider implements OAuthProviderInterface
{
    protected array $config;

    protected string $provider;

    public function __construct(array $config, string $provider)
    {
        $this->config = $config;
        $this->provider = $provider;
    }

    public function getAuthorizationUrl(string $state, array $scopes = []): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'state' => $state,
            'scope' => implode(' ', $scopes ?: $this->config['scopes']),
        ];

        return $this->config['authorization_url'] . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $state): array
    {
        $response = Http::asForm()->post($this->config['token_url'], [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new Exception("Token exchange failed for {$this->provider}: " . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? null,
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        if (! $this->supportsTokenRefresh()) {
            throw new Exception("Token refresh not supported for {$this->provider}");
        }

        $response = Http::asForm()->post($this->config['token_url'], [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new Exception("Token refresh failed for {$this->provider}: " . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $refreshToken,
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $data['scope'] ?? null,
        ];
    }

    public function makeApiCall(string $accessToken, string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->config['api_base_url'], '/') . '/' . ltrim($endpoint, '/');

        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'User-Agent' => 'Whilesmart-Integrations/1.0',
        ]);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'PUT' => $request->put($url, $data),
            'PATCH' => $request->patch($url, $data),
            'DELETE' => $request->delete($url, $data),
            default => throw new InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (config('integrations.logging.log_api_calls', false)) {
            Log::channel(config('integrations.logging.channel'))
                ->info("API call to {$this->provider}", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);
        }

        if (! $response->successful()) {
            throw new Exception("API call failed for {$this->provider}: " . $response->body());
        }

        return $response->json();
    }

    public function revokeToken(string $accessToken): bool
    {
        // Default implementation - override in specific providers if they support token revocation
        return true;
    }

    public function getName(): string
    {
        return $this->provider;
    }

    public function supportsTokenRefresh(): bool
    {
        return true; // Most OAuth providers support refresh tokens
    }

    abstract public function getUserInfo(string $accessToken): array;
}
