<?php

namespace Whilesmart\Integrations\Providers;

use stdClass;

class MicrosoftOneDriveProvider extends AbstractOAuthProvider
{
    public function getUserInfo(string $accessToken): array
    {
        $user = $this->makeApiCall($accessToken, 'GET', '/v1.0/me');

        return [
            'id' => $user['id'],
            'email' => $user['mail'] ?? $user['userPrincipalName'],
            'name' => $user['displayName'],
            'first_name' => $user['givenName'],
            'last_name' => $user['surname'],
            'job_title' => $user['jobTitle'],
            'office_location' => $user['officeLocation'],
            'mobile_phone' => $user['mobilePhone'],
        ];
    }

    public function getDrive(string $accessToken): array
    {
        return $this->makeApiCall($accessToken, 'GET', '/v1.0/me/drive');
    }

    public function getFiles(string $accessToken, string $folderId = 'root', array $params = []): array
    {
        $defaultParams = [
            '$top' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', "/v1.0/me/drive/items/{$folderId}/children", $params);
    }

    public function getFile(string $accessToken, string $fileId): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/v1.0/me/drive/items/{$fileId}");
    }

    public function createFolder(string $accessToken, string $name, string $parentId = 'root'): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/v1.0/me/drive/items/{$parentId}/children", [
            'name' => $name,
            'folder' => new stdClass(),
            '@microsoft.graph.conflictBehavior' => 'rename',
        ]);
    }

    public function uploadFile(string $accessToken, string $fileName, string $parentId = 'root'): array
    {
        // For small files (< 4MB) - simple upload
        $endpoint = "/v1.0/me/drive/items/{$parentId}:/{$fileName}:/content";

        return $this->makeApiCall($accessToken, 'PUT', $endpoint, []);
    }

    public function updateFile(string $accessToken, string $fileId, array $metadata): array
    {
        return $this->makeApiCall($accessToken, 'PATCH', "/v1.0/me/drive/items/{$fileId}", $metadata);
    }

    public function deleteFile(string $accessToken, string $fileId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/v1.0/me/drive/items/{$fileId}");
    }

    public function copyFile(string $accessToken, string $fileId, array $copyData): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/v1.0/me/drive/items/{$fileId}/copy", $copyData);
    }

    public function moveFile(string $accessToken, string $fileId, string $newParentId): array
    {
        return $this->makeApiCall($accessToken, 'PATCH', "/v1.0/me/drive/items/{$fileId}", [
            'parentReference' => [
                'id' => $newParentId,
            ],
        ]);
    }

    public function shareFile(string $accessToken, string $fileId, array $permissions): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/v1.0/me/drive/items/{$fileId}/createLink", $permissions);
    }

    public function getFilePermissions(string $accessToken, string $fileId): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/v1.0/me/drive/items/{$fileId}/permissions");
    }

    public function searchFiles(string $accessToken, string $query): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/v1.0/me/drive/search(q='{$query}')");
    }

    public function getRecentFiles(string $accessToken, array $params = []): array
    {
        $defaultParams = [
            '$top' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', '/v1.0/me/drive/recent', $params);
    }
}
