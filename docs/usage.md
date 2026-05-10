# Usage

Eloquent Webhooks provides a streamlined, simple API for managing webhooks programmatically within your application.

## Creating a Webhook

The primary way to manage webhooks is through the `Whilesmart\Webhooks\Models\Webhook` Eloquent model. When you create a webhook, the package automatically generates a secure token and the corresponding ingress URL.

```php
use Whilesmart\Webhooks\Models\Webhook;

$webhook = Webhook::create([
    'name' => 'Stripe Payment Events',
    'provider' => 'stripe',
    'event_type' => 'payment_intent.succeeded',
    'user_id' => $user->id,
    'workspace_id' => $workspace->id,
    'project_id' => $project->id,
    'is_active' => true,
]);

// The ingress URL that you provide to Stripe
echo $webhook->url; // https://your-app.com/webhooks/ingress/random-secure-token
```

## Querying Webhooks

Because webhooks are often tied to specific tenants (like workspaces or projects), the model includes helpful query scopes.

```php
// Retrieve all webhooks across the application that are active
$activeWebhooks = Webhook::active()->get();

// Retrieve all webhooks for a specific workspace
$workspaceWebhooks = Webhook::inWorkspace($workspace->id)->get();

// Retrieve all webhooks for a specific project
$projectWebhooks = Webhook::forProject($project->id)->get();
```

## Processing Incoming Webhooks

The package automatically handles incoming requests to the ingress URLs. When a third-party service makes a `POST` request to the webhook URL:

1. **Validation:** The secure token is extracted and verified. If it doesn't match an active webhook, a `404 Not Found` or `401 Unauthorized` response is returned.
2. **Event Logging:** The entire payload and headers are saved as a `WebhookEvent`.
3. **Tracking:** The `trigger_count` is incremented, and the `last_triggered_at` timestamp is updated.
4. **Activity Logging:** If the `whilesmart/activities` package is installed, an activity log is created.

You can then listen to the `WebhookEvent` model's `created` event to dispatch your own jobs that actually process the business logic of the webhook.

```php
// In an EventServiceProvider or Observer
use Whilesmart\Webhooks\Models\WebhookEvent;

WebhookEvent::created(function (WebhookEvent $event) {
    if ($event->webhook->provider === 'stripe') {
        ProcessStripeWebhookJob::dispatch($event);
    }
});
```
