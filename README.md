# Multi-tenant SSO (OIDC + SAML 2.0) for Laravel.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)

Generic OIDC and SAML 2.0 SSO for Laravel, with multi-tenant support, user provisioning, identity linking, and redacted audit logging.

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
## Audit logging
Audit logging is redacted by default.

Successful callback audits retain only concise metadata such as:
- protocol
- tenant, connection, and attempt identifiers
- status and error classification
- a truncated subject hint plus a one-way subject hash
- claim keys and bounded protocol flags such as userinfo_used or SAML signature booleans

The package does not store raw OIDC tokens, raw SAML assertions, or full claim payloads in sso_audit_logs.context by default.

## Extended audit context

Extended audit context is available only as an explicit opt-in for debugging:
```php
'audit' => [
    'extended_context' => false,
],
```
When enabled, the package stores additional redacted summaries for canonical claims and driver context. Sensitive values such as access tokens, ID tokens, refresh tokens, private keys, SAML responses, and raw claim bags remain redacted even in extended mode.

## Retention guidance

Treat sso_audit_logs as security telemetry rather than a data warehouse. Apply a retention policy appropriate to your compliance posture and keep the extended audit context disabled outside controlled debugging windows.

## Provisioning and identity-linking policies
Provisioning and identity linking are **deny-by-default.**

A successful OIDC or SAML callback only creates or links a local user when one of the following is true:
1.	the host application binds its own ProvisioningPolicy / IdentityLinkPolicy,
2.	package-wide defaults are enabled in config/sso.php, or
3.	the connection explicitly opts in through sso_connections.settings.

## Package-wide defaults
```php
'provisioning' => [
    'enabled_by_default' => false,
],

'linking' => [
    'enabled_by_default' => false,
],
```
Set either value to true only when that behavior is acceptable for your application.

## Per-connection overrides
Connection settings take precedence over package defaults.
```php
$connection->settings = [
    'allow_provisioning' => true,
    'allow_identity_linking' => true,
];
```
This makes it possible to allow automatic provisioning or linking for one identity provider while denying it for another within the same tenant.

## Explicit allow policies

The package still ships permissive policy implementations for host applications that want to opt in globally:
- CreativeCrafts\LaravelSso\Policies\AllowProvisioningPolicy
- CreativeCrafts\LaravelSso\Policies\AllowIdentityLinkPolicy

Bind them explicitly in your application service provider if you want that behavior.

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
