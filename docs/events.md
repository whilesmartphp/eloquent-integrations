# Events & Logging

Eloquent Webhooks provides comprehensive tracking for all incoming requests, allowing you to audit, debug, and trace webhook history effortlessly.

## The WebhookEvent Model

Every time a valid request hits a webhook's ingress URL, a `WebhookEvent` is automatically created. This record contains the entire context of the request.

```php
$event = $webhook->events()->latest()->first();

// The original payload sent by the provider
$event->payload;

// The HTTP headers included in the request
$event->headers;
```

## Trigger Tracking

The `Webhook` model itself keeps track of how many times it has been triggered and when the last trigger occurred.

```php
// Check how many times this webhook has received a payload
echo $webhook->trigger_count;

// Get a Carbon instance of the last received payload
echo $webhook->last_triggered_at->diffForHumans();
```

## Integration with Activity Logs

If you are using the `whilesmart/activities` package, Eloquent Webhooks will seamlessly integrate with it.

When a webhook processes a payload, an Activity log entry is generated automatically. This is especially useful for maintaining a unified audit trail across your application.
