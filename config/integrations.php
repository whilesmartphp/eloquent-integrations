<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Integrations Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file manages third-party integrations including
    | OAuth providers, webhooks, and external service connections.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ownership Models
    |--------------------------------------------------------------------------
    |
    | Configure default models used by package controllers for user and
    | workspace-scoped ownership helpers.
    |
    */
    'user_model' => env('INTEGRATION_USER_MODEL', 'App\\Models\\User'),

    'workspace_model' => env('INTEGRATION_WORKSPACE_MODEL', 'Whilesmart\\Workspaces\\Models\\Workspace'),

    /*
    |--------------------------------------------------------------------------
    | Storage Mode
    |--------------------------------------------------------------------------
    |
    | managed_locally: Store encrypted tokens or package credentials locally.
    | external_vault: Store only pointers to a third-party vault such as Nango.
    |
    */
    'default_mode' => env('INTEGRATION_DEFAULT_MODE', 'managed_locally'),

    'external_vaults' => [
        'nango' => [
            'enabled' => env('NANGO_ENABLED', false),
            'base_url' => env('NANGO_BASE_URL', 'https://api.nango.dev'),
            'secret_key' => env('NANGO_SECRET_KEY'),
            'webhook_secret' => env('NANGO_WEBHOOK_SECRET'),
            'connection_id_prefix' => env('NANGO_CONNECTION_ID_PREFIX', env('APP_NAME', 'app')),
        ],
    ],

    'nango_providers' => [
        'github' => [
            'enabled' => env('NANGO_GITHUB_ENABLED', env('NANGO_ENABLED', false)),
            'name' => 'GitHub',
            'provider_config_key' => env('NANGO_GITHUB_PROVIDER_CONFIG_KEY', 'github'),
            'scopes' => ['repo', 'user:email'],
        ],
        'slack' => [
            'enabled' => env('NANGO_SLACK_ENABLED', env('NANGO_ENABLED', false)),
            'name' => 'Slack',
            'provider_config_key' => env('NANGO_SLACK_PROVIDER_CONFIG_KEY', 'slack'),
            'scopes' => ['channels:read', 'users:read'],
        ],
        'google_calendar' => [
            'enabled' => env('NANGO_GOOGLE_CALENDAR_ENABLED', env('NANGO_ENABLED', false)),
            'name' => 'Google Calendar',
            'provider_config_key' => env('NANGO_GOOGLE_CALENDAR_PROVIDER_CONFIG_KEY', 'google-calendar'),
            'scopes' => ['https://www.googleapis.com/auth/calendar'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    |
    | Configure OAuth providers for third-party authentication and API access.
    | Each provider can be enabled/disabled and configured with their
    | specific client credentials and scopes.
    |
    */
    'oauth_providers' => [
        'github' => [
            'enabled' => env('INTEGRATION_GITHUB_ENABLED', false),
            'client_id' => env('INTEGRATION_GITHUB_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_GITHUB_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_GITHUB_REDIRECT_URI', '/integrations/oauth/github/callback'),
            'scopes' => ['user:email', 'repo'],
            'authorization_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'api_base_url' => 'https://api.github.com',
        ],

        'google_calendar' => [
            'enabled' => env('INTEGRATION_GOOGLE_CALENDAR_ENABLED', false),
            'client_id' => env('INTEGRATION_GOOGLE_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_GOOGLE_CALENDAR_REDIRECT_URI', '/integrations/oauth/google-calendar/callback'),
            'scopes' => ['email', 'profile', 'https://www.googleapis.com/auth/calendar'],
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'api_base_url' => 'https://www.googleapis.com',
        ],

        'google_drive' => [
            'enabled' => env('INTEGRATION_GOOGLE_DRIVE_ENABLED', false),
            'client_id' => env('INTEGRATION_GOOGLE_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_GOOGLE_DRIVE_REDIRECT_URI', '/integrations/oauth/google-drive/callback'),
            'scopes' => ['email', 'profile', 'https://www.googleapis.com/auth/drive'],
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'api_base_url' => 'https://www.googleapis.com',
        ],

        'slack' => [
            'enabled' => env('INTEGRATION_SLACK_ENABLED', false),
            'client_id' => env('INTEGRATION_SLACK_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_SLACK_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_SLACK_REDIRECT_URI', '/integrations/oauth/slack/callback'),
            'scopes' => ['channels:read', 'chat:write', 'users:read'],
            'authorization_url' => 'https://slack.com/oauth/v2/authorize',
            'token_url' => 'https://slack.com/api/oauth.v2.access',
            'api_base_url' => 'https://slack.com/api',
        ],

        'microsoft_calendar' => [
            'enabled' => env('INTEGRATION_MICROSOFT_CALENDAR_ENABLED', false),
            'client_id' => env('INTEGRATION_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_MICROSOFT_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_MICROSOFT_CALENDAR_REDIRECT_URI', '/integrations/oauth/microsoft-calendar/callback'),
            'scopes' => ['User.Read', 'Calendars.ReadWrite'],
            'authorization_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'api_base_url' => 'https://graph.microsoft.com',
        ],

        'microsoft_onedrive' => [
            'enabled' => env('INTEGRATION_MICROSOFT_ONEDRIVE_ENABLED', false),
            'client_id' => env('INTEGRATION_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('INTEGRATION_MICROSOFT_CLIENT_SECRET'),
            'redirect_uri' => env('INTEGRATION_MICROSOFT_ONEDRIVE_REDIRECT_URI', '/integrations/oauth/microsoft-onedrive/callback'),
            'scopes' => ['User.Read', 'Files.ReadWrite'],
            'authorization_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'api_base_url' => 'https://graph.microsoft.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Storage
    |--------------------------------------------------------------------------
    |
    | Configure how integration data is stored and managed.
    |
    */
    'storage' => [
        'encrypt_tokens' => env('INTEGRATION_ENCRYPT_TOKENS', true),
        'token_expiry_buffer' => env('INTEGRATION_TOKEN_EXPIRY_BUFFER', 300), // 5 minutes
        'auto_refresh_tokens' => env('INTEGRATION_AUTO_REFRESH_TOKENS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Integration
    |--------------------------------------------------------------------------
    |
    | Configure integration with workspaces if available.
    |
    */
    'workspace_scoped' => env('INTEGRATION_WORKSPACE_SCOPED', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API calls to third-party services.
    |
    */
    'rate_limiting' => [
        'enabled' => env('INTEGRATION_RATE_LIMITING_ENABLED', true),
        'default_limit' => env('INTEGRATION_DEFAULT_RATE_LIMIT', 100), // requests per minute
        'provider_limits' => [
            'github' => 5000, // GitHub API limit
            'google_calendar' => 100,
            'google_drive' => 100,
            'slack' => 100,
            'microsoft_calendar' => 100,
            'microsoft_onedrive' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for integration activities.
    |
    */
    'logging' => [
        'enabled' => env('INTEGRATION_LOGGING_ENABLED', true),
        'channel' => env('INTEGRATION_LOG_CHANNEL', 'default'),
        'log_api_calls' => env('INTEGRATION_LOG_API_CALLS', false),
        'log_token_refresh' => env('INTEGRATION_LOG_TOKEN_REFRESH', true),
        'log_errors' => env('INTEGRATION_LOG_ERRORS', true),
    ],
];
