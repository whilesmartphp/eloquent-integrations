<?php

namespace Whilesmart\Integrations\Providers;

class GoogleDriveProvider extends AbstractOAuthProvider
{
    public function getUserInfo(string $accessToken): array
    {
        $user = $this->makeApiCall($accessToken, 'GET', '/oauth2/v2/userinfo');

        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'first_name' => $user['given_name'],
            'last_name' => $user['family_name'],
            'avatar_url' => $user['picture'],
            'locale' => $user['locale'],
            'verified_email' => $user['verified_email'] ?? false,
        ];
    }

    public function getFiles(string $accessToken, array $params = []): array
    {
        $defaultParams = [
            'pageSize' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', '/drive/v3/files', $params);
    }

    public function getFile(string $accessToken, string $fileId, array $params = []): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/drive/v3/files/{$fileId}", $params);
    }

    public function createFile(string $accessToken, array $fileData): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/drive/v3/files', $fileData);
    }

    public function updateFile(string $accessToken, string $fileId, array $fileData): array
    {
        return $this->makeApiCall($accessToken, 'PATCH', "/drive/v3/files/{$fileId}", $fileData);
    }

    public function deleteFile(string $accessToken, string $fileId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/drive/v3/files/{$fileId}");
    }

    public function copyFile(string $accessToken, string $fileId, array $copyData): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/drive/v3/files/{$fileId}/copy", $copyData);
    }

    public function createFolder(string $accessToken, string $name, array $parents = []): array
    {
        return $this->createFile($accessToken, [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parents,
        ]);
    }

    public function shareFile(string $accessToken, string $fileId, array $permissionData): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/drive/v3/files/{$fileId}/permissions", $permissionData);
    }

    public function getPermissions(string $accessToken, string $fileId): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/drive/v3/files/{$fileId}/permissions");
    }

    public function searchFiles(string $accessToken, string $query, array $params = []): array
    {
        $params['q'] = $query;

        return $this->getFiles($accessToken, $params);
    }
}
