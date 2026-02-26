# Multi-tenant SSO (OIDC + SAML 2.0) for Laravel.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)

Generic OIDC and SAML 2.0 SSO for Laravel, with multi-tenant support, user provisioning, and identity linking.

## Installation

You can install the package via composer:

```bash
composer require creativecrafts/laravel-sso
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="sso-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="sso-config"
```
## Optional Admin UI (bootstrap)
The admin UI is **optional** and disabled by default.
Enable it in your config (or .env if you map env vars in your app):

```php
// config/sso.php
'ui' => [
    'enabled' => true,
],
```
The UI routes are protected by your configured middleware (default `web` + `auth`) and a gate ability (default `manageSso`).

## Publish UI assets

```bash
php artisan vendor:publish --tag="sso-ui"
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
