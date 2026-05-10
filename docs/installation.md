# Installation

You can install the package via composer:

```bash
composer require whilesmart/eloquent-integrations
```

You can optionally publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --provider="Whilesmart\Integrations\IntegrationsServiceProvider"
```

## Suggested Dependencies

To unlock additional features, you may want to install the suggested packages:

- `whilesmart/eloquent-client-credentials`: For reusable local credential and secret storage.
- `whilesmart/eloquent-workspaces`: For workspace-scoped integrations.
- `laravel/socialite`: For OAuth provider support.
