<?php

namespace Whilesmart\Integrations\Providers;

class GitHubProvider extends AbstractOAuthProvider
{
    public function getUserInfo(string $accessToken): array
    {
        $user = $this->makeApiCall($accessToken, 'GET', '/user');

        return [
            'id' => $user['id'],
            'username' => $user['login'],
            'email' => $user['email'],
            'name' => $user['name'],
            'avatar_url' => $user['avatar_url'],
            'profile_url' => $user['html_url'],
            'company' => $user['company'],
            'location' => $user['location'],
            'bio' => $user['bio'],
        ];
    }

    public function getRepositories(string $accessToken, array $params = []): array
    {
        $defaultParams = [
            'type' => 'all',
            'sort' => 'updated',
            'per_page' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', '/user/repos', $params);
    }

    public function createRepository(string $accessToken, array $repoData): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/user/repos', $repoData);
    }

    public function getRepository(string $accessToken, string $owner, string $repo): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/repos/{$owner}/{$repo}");
    }

    public function createWebhook(string $accessToken, string $owner, string $repo, array $webhookData): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/repos/{$owner}/{$repo}/hooks", $webhookData);
    }

    public function getCommits(string $accessToken, string $owner, string $repo, array $params = []): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/repos/{$owner}/{$repo}/commits", $params);
    }

    public function getIssues(string $accessToken, string $owner, string $repo, array $params = []): array
    {
        $defaultParams = [
            'state' => 'open',
            'per_page' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', "/repos/{$owner}/{$repo}/issues", $params);
    }

    public function createIssue(string $accessToken, string $owner, string $repo, array $issueData): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/repos/{$owner}/{$repo}/issues", $issueData);
    }
}
