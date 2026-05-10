# Eloquent Webhooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/whilesmart/eloquent-webhooks/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/whilesmart/eloquent-webhooks/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)

A comprehensive webhook management package for Laravel applications. Easily manage, track, and process incoming webhooks with built-in support for user, workspace, and project scoping.

## Overview

**Eloquent Webhooks** is built to take the pain out of managing incoming webhooks in multi-tenant or complex Laravel applications. Whether you're receiving data from GitHub, Stripe, or custom internal services, this package provides a robust foundation for securely receiving payloads, auditing events, and tying them back to your application's domain models.

### Key Features

- **Webhook Management:** Complete CRUD operations for webhooks through pre-configured controllers.
- **Secure Ingress:** Automatic cryptographically secure token generation for unique webhook endpoints.
- **Event Auditing:** Automatically logs all incoming webhook payloads, headers, and processing status.
- **Scoped by Default:** Built-in support for filtering webhooks by User, Workspace, and Project.
- **Soft Deletes:** Safely archive webhooks without losing historical data.
- **Flexible Integration:** Seamlessly integrates with other WhileSmart packages like `whilesmart/eloquent-workspaces`, `whilesmart/projects`, and `whilesmart/activities`.
- **API Ready:** Comes with endpoints out-of-the-box for rapid frontend development.

---

## Installation

You can install the package via composer:

```bash
composer require whilesmart/eloquent-webhooks
```

Publish and run the necessary database migrations to set up the `webhooks` and `webhook_events` tables:

```bash
php artisan vendor:publish --tag="webhooks-migrations"
php artisan migrate
```

You can optionally publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --tag="webhooks-config"
```

---

## Configuration & Customization

The package provides a flexible configuration file located at `config/webhooks.php`. You can use environment variables or edit the config directly to tailor the package to your needs.

### Custom Models
By default, the package links webhooks to the standard `App\Models\User`, `App\Models\Workspace`, and `App\Models\Project` models. You can change this behavior:

```php
'user_model' => env('WEBHOOKS_USER_MODEL', 'App\Models\User'),
'workspace_model' => env('WEBHOOKS_WORKSPACE_MODEL', 'App\Models\Workspace'),
'project_model' => env('WEBHOOKS_PROJECT_MODEL', 'App\Models\Project'),
```

### Feature Flags
Depending on your application's architecture, you may want to disable certain scopes or tracking features:

```php
'workspace_scoped' => env('WEBHOOKS_WORKSPACE_SCOPED', true),
'project_scoped' => env('WEBHOOKS_PROJECT_SCOPED', true),
'track_events' => env('WEBHOOKS_TRACK_EVENTS', true),
```

---

## Usage

### Managing Webhooks Programmatically

The primary model provided by the package is `Whilesmart\Webhooks\Models\Webhook`.

```php
use Whilesmart\Webhooks\Models\Webhook;

// Create a new Webhook
$webhook = Webhook::create([
    'name' => 'Stripe Payment Events',
    'provider' => 'stripe',
    'event_type' => 'payment_intent.succeeded',
    'user_id' => $user->id,
    'workspace_id' => $workspace->id,
    'project_id' => $project->id,
    'secret' => 'whsec_...', // Optional signing secret
    'is_active' => true,
]);

// Access the automatically generated secure ingress URL
echo $webhook->url; // https://your-app.com/webhooks/ingress/random-secure-token
```

### Scoping Webhooks

The `Webhook` model includes several query scopes to easily retrieve webhooks relevant to a specific context:

```php
// Get all active webhooks
$activeWebhooks = Webhook::active()->get();

// Get webhooks belonging to a specific workspace
$workspaceWebhooks = Webhook::inWorkspace($workspace->id)->get();

// Get webhooks belonging to a specific project
$projectWebhooks = Webhook::forProject($project->id)->get();
```

---

## Webhook Ingress & Security

Security is a primary concern when exposing endpoints to the open internet.

### Secure Tokens
Upon creation, every webhook generates a secure, random 40-character `token`. This token forms the unique ingress URL. When a third-party service makes a `POST` request to this URL, the package validates the token. If the token is invalid or the webhook is set to `is_active = false`, the request is rejected immediately.

### Rotating Tokens
If an ingress URL is compromised, you can instantly revoke access by regenerating the token:

```php
$webhook->regenerateToken();
echo $webhook->url; // Outputs the new, secure URL
```

---

## Events & Auditing

Whenever a valid payload is received at a webhook's ingress URL, the package performs several automated tracking steps:

1. **Trigger Tracking:** The `trigger_count` is incremented and `last_triggered_at` is updated on the `Webhook` model.
2. **Event Logging:** If `track_events` is enabled, a `WebhookEvent` model is created containing the full context of the request, including headers and the JSON payload.
3. **Activity Logging:** If the `whilesmart/activities` package is installed and configured, an activity entry is automatically logged.

### Accessing Events

You can easily inspect the events received by a webhook:

```php
// Retrieve the 5 most recent payloads received
$recentEvents = $webhook->events()->latest()->take(5)->get();

foreach ($recentEvents as $event) {
    dump($event->payload);
    dump($event->headers);
}
```

---

## API Endpoints

The package can automatically register standard API routes to manage webhooks from your frontend. These routes are protected by the `auth:sanctum` middleware by default (configurable in `config/webhooks.php`).

### General Management
- `GET /webhooks` - List user's webhooks
- `POST /webhooks` - Create a new webhook
- `GET /webhooks/{id}` - Show webhook details
- `PATCH /webhooks/{id}` - Update a webhook
- `DELETE /webhooks/{id}` - Soft delete a webhook
- `GET /webhooks/{id}/events` - List event history

### Scoped Routes
If you are building a multi-tenant application, you may interact with the workspace-scoped routes:
- `GET /workspaces/{workspaceId}/webhooks`
- `POST /workspaces/{workspaceId}/webhooks`

---

## Testing

You can run the included test suite to verify the package functions correctly in your environment:

```bash
composer test
```

For style and static analysis, you can use:
```bash
composer lint
composer phpstan
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
