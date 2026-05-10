# Verification & Security

Security is a primary concern when dealing with webhooks. **Eloquent Webhooks** ensures that incoming requests are legitimate and prevents unauthorized data ingestion.

## Webhook Tokens

When a webhook is created, a unique, cryptographically secure 40-character `token` is automatically generated. This token is used to construct a unique ingress URL.

```php
// Automatically generated token upon creation
$webhook->token; // "random-40-character-string"

// Corresponding ingress URL
$webhook->url; // "https://your-app.com/webhooks/ingress/random-40-character-string"
```

The system verifies this token on every incoming request. If the token is invalid or the webhook is marked as inactive (`is_active = false`), the request is rejected with a `401 Unauthorized` or `404 Not Found` response.

## Rotating Tokens

If you suspect a webhook URL has been compromised, you can easily rotate its token using the built-in `regenerateToken()` method.

```php
$webhook->regenerateToken();

// The webhook now has a new token and URL
echo $webhook->url;
```

This invalidates the old URL immediately, requiring the third-party service to be updated with the new ingress URL.

## Provider Secrets

For providers that send a signature header (like GitHub, Stripe, or Stripe), you can store a `secret` on the Webhook model. This secret can be utilized in your custom webhook handling logic to verify the HMAC signature of the incoming payload, providing an additional layer of security beyond the obscure ingress URL.

```php
$webhook = Webhook::create([
    // ...
    'provider' => 'github',
    'secret' => 'your-webhook-secret-from-github'
]);
```
