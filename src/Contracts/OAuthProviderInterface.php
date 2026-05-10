<?php

namespace Whilesmart\Integrations\Contracts;

interface OAuthProviderInterface
{
    /**
     * Get the authorization URL for the OAuth flow.
     */
    public function getAuthorizationUrl(string $state, array $scopes = []): string;

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $code, string $state): array;

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Get user information from the provider.
     */
    public function getUserInfo(string $accessToken): array;

    /**
     * Make an API call to the provider.
     */
    public function makeApiCall(string $accessToken, string $method, string $endpoint, array $data = []): array;

    /**
     * Revoke an access token.
     */
    public function revokeToken(string $accessToken): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Check if the provider supports token refresh.
     */
    public function supportsTokenRefresh(): bool;
}
