<?php

namespace Whilesmart\Integrations\Providers;

class MicrosoftCalendarProvider extends AbstractOAuthProvider
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

    public function getCalendars(string $accessToken): array
    {
        return $this->makeApiCall($accessToken, 'GET', '/v1.0/me/calendars');
    }

    public function getEvents(string $accessToken, ?string $calendarId = null, array $params = []): array
    {
        $endpoint = $calendarId ? "/v1.0/me/calendars/{$calendarId}/events" : '/v1.0/me/events';

        $defaultParams = [
            '$top' => 100,
            '$orderby' => 'start/dateTime',
        ];

        $params = array_merge($defaultParams, $params);

        return $this->makeApiCall($accessToken, 'GET', $endpoint, $params);
    }

    public function createEvent(string $accessToken, array $eventData, ?string $calendarId = null): array
    {
        $endpoint = $calendarId ? "/v1.0/me/calendars/{$calendarId}/events" : '/v1.0/me/events';

        return $this->makeApiCall($accessToken, 'POST', $endpoint, $eventData);
    }

    public function getEvent(string $accessToken, string $eventId): array
    {
        return $this->makeApiCall($accessToken, 'GET', "/v1.0/me/events/{$eventId}");
    }

    public function updateEvent(string $accessToken, string $eventId, array $eventData): array
    {
        return $this->makeApiCall($accessToken, 'PATCH', "/v1.0/me/events/{$eventId}", $eventData);
    }

    public function deleteEvent(string $accessToken, string $eventId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/v1.0/me/events/{$eventId}");
    }

    public function getFreeBusy(string $accessToken, array $emails, string $startTime, string $endTime): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/v1.0/me/calendar/getFreeBusy', [
            'schedules' => $emails,
            'startTime' => [
                'dateTime' => $startTime,
                'timeZone' => 'UTC',
            ],
            'endTime' => [
                'dateTime' => $endTime,
                'timeZone' => 'UTC',
            ],
        ]);
    }

    public function createCalendar(string $accessToken, array $calendarData): array
    {
        return $this->makeApiCall($accessToken, 'POST', '/v1.0/me/calendars', $calendarData);
    }

    public function updateCalendar(string $accessToken, string $calendarId, array $calendarData): array
    {
        return $this->makeApiCall($accessToken, 'PATCH', "/v1.0/me/calendars/{$calendarId}", $calendarData);
    }

    public function deleteCalendar(string $accessToken, string $calendarId): array
    {
        return $this->makeApiCall($accessToken, 'DELETE', "/v1.0/me/calendars/{$calendarId}");
    }
}
