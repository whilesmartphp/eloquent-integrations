# Eloquent Integrations

[![Latest Version on Packagist](https://img.shields.io/packagist/v/whilesmart/eloquent-integrations.svg?style=flat-square)](https://packagist.org/packages/whilesmart/eloquent-integrations)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/whilesmart/eloquent-integrations/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/whilesmart/eloquent-integrations/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/whilesmart/eloquent-integrations.svg?style=flat-square)](https://packagist.org/packages/whilesmart/eloquent-integrations)

OAuth and third-party integrations package for Laravel applications. Easily manage and store API credentials, tokens, and integration settings for various third-party services.

## Overview

**Eloquent Integrations** provides a robust framework for handling OAuth and API integrations in your Laravel applications. It simplifies the process of connecting to external services, securely storing credentials, and scoping integrations to specific entities like users or workspaces.

### Key Features

- **OAuth Support:** Seamlessly integrates with Laravel Socialite for managing OAuth flows.
- **Credential Storage:** Securely store and manage API keys, access tokens, and refresh tokens.
- **Workspace Scoping:** Built-in support for scoping integrations to specific workspaces using `whilesmart/eloquent-workspaces`.
- **Reusable Credentials:** Leverage `whilesmart/eloquent-client-credentials` for local credential and secret storage.

---

## Installation

You can install the package via composer:

```bash
composer require whilesmart/eloquent-integrations
```

You can optionally publish the configuration file to customize the default behavior:

```bash
php artisan vendor:publish --provider="Whilesmart\Integrations\IntegrationsServiceProvider"
```

---

## Suggested Dependencies

To unlock additional features, you may want to install the suggested packages:

- `whilesmart/eloquent-client-credentials`: For reusable local credential and secret storage.
- `whilesmart/eloquent-workspaces`: For workspace-scoped integrations.
- `laravel/socialite`: For OAuth provider support.

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
