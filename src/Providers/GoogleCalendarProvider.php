<?php

namespace Whilesmart\Integrations\Providers;

class GoogleCalendarProvider extends AbstractOAuthProvider
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

    public function createCalendar(string $accessToken, array $calendarData): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/calendar/v3/calendars', $calendarData);
    }

    public function getCalendar(string $accessToken, string $calendarId): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/calendar/v3/calendars/{$calendarId}");
    }

    public function updateCalendar(string $accessToken, string $calendarId, array $calendarData): array
    {
        return $this->makeApiCall($accessToken, 'PUT', "/calendar/v3/calendars/{$calendarId}", $calendarData);
    }

    public function deleteCalendar(string $accessToken, string $calendarId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/calendar/v3/calendars/{$calendarId}");
    }

    public function getFreeBusy(string $accessToken, array $calendars, string $timeMin, string $timeMax): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/calendar/v3/freeBusy', [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'items' => array_map(fn ($cal) => ['id' => $cal], $calendars),
        ]);
    }
}
