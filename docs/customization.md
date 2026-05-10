# Customization

Eloquent Integrations provides a flexible configuration file to tailor the package to your needs.

## Configuration

First, publish the configuration file if you haven't already:

```bash
php artisan vendor:publish --provider="Whilesmart\Integrations\IntegrationsServiceProvider"
```

This will create a `config/integrations.php` (or similar depending on your setup) file in your application where you can customize various settings related to your OAuth and third-party integrations.

## Workspace Scoping

If you are using `whilesmart/eloquent-workspaces`, you can enable workspace-scoped integrations in the configuration file to ensure all stored credentials and API tokens are properly isolated by workspace.

## Storage and Credentials

You can leverage `whilesmart/eloquent-client-credentials` for secure local credential and secret storage. Ensure you configure the appropriate storage driver and encryption keys as needed by your application's security requirements.
