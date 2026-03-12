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

## Lifecycle events

The package emits events across both the begin-login and callback lifecycle so host applications can attach telemetry, webhooks, or post-login actions without modifying package internals.

**Begin-login events**
- CreativeCrafts\LaravelSso\Events\BeginLoginRequested
- CreativeCrafts\LaravelSso\Events\AuthAttemptCreated
- CreativeCrafts\LaravelSso\Events\BeginLoginRedirectGenerated

**Callback and completion events**
- CreativeCrafts\LaravelSso\Events\CallbackSucceeded
- CreativeCrafts\LaravelSso\Events\CallbackFailed
- CreativeCrafts\LaravelSso\Events\UserProvisioned
- CreativeCrafts\LaravelSso\Events\IdentityLinked
- CreativeCrafts\LaravelSso\Events\LoginCompleted

A typical successful first-login flow emits these callback-side events in order:
1.	CallbackSucceeded
2.	UserProvisioned
3.	IdentityLinked
4.	LoginCompleted

A successful login through an existing external identity emits:
1.	CallbackSucceeded
2.	LoginCompleted

A successful login that links an existing local user by email emits:
1.	CallbackSucceeded
2.	IdentityLinked
3.	LoginCompleted

**Example listener**
```php
use CreativeCrafts\LaravelSso\Events\LoginCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(LoginCompleted::class, function (LoginCompleted $event): void {
    $user = $event->user;

    if (method_exists($user, 'forceFill')) {
        $user->forceFill(['last_login_at' => now()])->save();
    }
});
```
These events are intended for in-process extension and observability. Persisted audit data remains redacted separately through sso_audit_logs.

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

## Request throttling

Public SSO endpoints are rate limited by default.

The package applies independent throttle buckets to:
- sso.redirect
- sso.oidc.callback
- sso.saml.acs

Default limits are intentionally conservative:
```php
'throttling' => [
    'redirect' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
    'callback' => [
        'enabled' => true,
        'max_attempts' => 30,
        'decay_minutes' => 1,
    ],
    'acs' => [
        'enabled' => true,
        'max_attempts' => 30,
        'decay_minutes' => 1,
    ],
],
```

**Tuning limits**

For higher-traffic deployments, raise the relevant bucket without changing the others. For example, a deployment with a bursty login initiation pattern can increase the redirect bucket while keeping callback and ACS more restrictive.
```php
'throttling' => [
    'redirect' => [
        'enabled' => true,
        'max_attempts' => 120,
        'decay_minutes' => 1,
    ],
],
```

**Disabling throttling**

Disabling endpoint throttling is supported but not recommended for production.
```php
'throttling' => [
    'callback' => [
        'enabled' => false,
    ],
],
```
Only disable a limiter when you have compensating protections in front of the package, such as a trusted upstream WAF or gateway policy.

## End-to-end integration tests

The package includes full-flow integration coverage for both OIDC and SAML on top of the lower-level protocol and unit tests.

These tests exercise the browser-facing login lifecycle end to end:
- redirect initiation through sso.redirect
- auth attempt creation and state / relay binding
- provider callback dispatch through the real HTTP controllers
- user provisioning and external identity linking
- guard authentication and final redirect handling
- selected negative paths such as expired attempts, nonce mismatch, replay, invalid signatures, and policy-denied provisioning

**Running the integration suite**
Run the full package test suite:
```bash
composer test
```
Run the end-to-end flow tests directly:
```bash
php artisan test tests/Feature/Oidc/LoginFinalizationTest.php
php artisan test tests/Feature/Saml/SamlLoginFinalizationTest.php
php artisan test tests/Feature/Saml/SamlReplayPreventionTest.php
```

**Test identity provider infrastructure**
The integration tests avoid external network dependencies while still exercising full controller dispatch:
- OIDC full-flow tests use the real OidcDriver with Http::fake() for token, JWKS, and optional userinfo endpoints.
- OIDC JWT material is generated by tests/Support/OidcTestJwt.php.
- SAML full-flow tests use signed XML fixtures generated by tests/Support/SamlTestXmlFactory.php.

**Updating fixtures when protocol behavior changes**

When OIDC or SAML behavior evolves, update the test infrastructure in this order:
1.	update the protocol-specific helper or fixture generator
		- tests/Support/OidcTestJwt.php
		- tests/Support/SamlTestXmlFactory.php
2.	update the lower-level protocol tests
3.  update the end-to-end login finalization tests only after the helpers reflect the new protocol behavior

This keeps fixture drift isolated and makes regressions easier to diagnose.

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
