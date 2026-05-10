<?php

namespace Whilesmart\Integrations\Providers;

class GoogleProvider extends AbstractOAuthProvider
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

    public function getCalendars(string $accessToken): array
    {
        return $this->makeApiCall($accessToken, 'GET', '/calendar/v3/users/me/calendarList');
    }

    public function getCalendarEvents(string $accessToken, string $calendarId = 'primary', array $params = []): array
    {
        $defaultParams = [
            'timeMin' => now()->toISOString(),
            'maxResults' => 100,
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', "/calendar/v3/calendars/{$calendarId}/events", $params);
    }

    public function createCalendarEvent(string $accessToken, string $calendarId = 'primary', array $eventData = []): array
    {
        return $this->makeApiCall($accessToken, 'POST', "/calendar/v3/calendars/{$calendarId}/events", $eventData);
    }

    public function updateCalendarEvent(string $accessToken, string $calendarId, string $eventId, array $eventData): array
    {
        return $this->makeApiCall($accessToken, 'PUT', "/calendar/v3/calendars/{$calendarId}/events/{$eventId}", $eventData);
    }

    public function deleteCalendarEvent(string $accessToken, string $calendarId, string $eventId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/calendar/v3/calendars/{$calendarId}/events/{$eventId}");
    }

    public function getDriveFiles(string $accessToken, array $params = []): array
    {
        $defaultParams = [
            'pageSize' => 100,
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', '/drive/v3/files', $params);
    }

    public function uploadFile(string $accessToken, array $fileData): array
    {
        // This is a simplified implementation - real file upload would need multipart handling
        return $this->makeApiCall($accessToken, 'POST', '/upload/drive/v3/files', [
            'name' => $fileData['name'],
            'parents' => $fileData['parents'] ?? [],
        ]);
    }
}
