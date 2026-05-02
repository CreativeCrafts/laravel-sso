# Multi-tenant SSO (OIDC + SAML 2.0) for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)
[![GitHub CI Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/ci.yml?branch=main&label=ci&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)

Generic OIDC and SAML 2.0 SSO for Laravel, with multi-tenant support, user provisioning, identity linking, redacted audit logging, replay prevention, and configurable throttling on public SSO endpoints.

## Compatibility

This package currently supports the runtime constraints declared in `composer.json`:

- PHP `^8.3`
- Laravel / Illuminate `^12.0|^13.0`

CI runs the package checks on PHP 8.3 and PHP 8.4. See `composer.json` for the authoritative runtime constraints.

## Documentation

For operator-facing and release-facing guidance, see:

- [Operator Guide](docs/operator-guide.md)
- [Getting Started](docs/getting-started.md)
- [Error Catalog](docs/error-catalog.md)
- [Deployment Guide](docs/deployment-guide.md)
- [Troubleshooting Guide](docs/troubleshooting.md)
- [Upgrade Guide](docs/upgrade-guide.md)
- [Maintainer Release Checklist](docs/maintainer-release-checklist.md)
- [Architecture Audit](docs/architecture-audit.md)

## Installation

Install the package with Composer:

~~~bash
composer require creativecrafts/laravel-sso
~~~

Publish and run the migrations:

~~~bash
php artisan vendor:publish --tag="sso-migrations"
php artisan migrate
~~~

Publish the configuration:

~~~bash
php artisan vendor:publish --tag="sso-config"
~~~

## Database foundation

This package depends on its schema. The published migration creates the package tables used for:

- tenants
- identity providers
- connections
- auth attempts
- external identities
- audit logs

Do not treat the database layer as optional. The package assumes these tables exist and are current.

## Documentation structure

This repository separates concise package-facing documentation from operator-facing deployment and incident guidance.

Start here for operations and production rollout:

- `docs/operator-guide.md`
- `docs/error-catalog.md`
- `docs/deployment-guide.md`
- `docs/troubleshooting.md`
- `docs/upgrade-guide.md`
- `docs/maintainer-release-checklist.md`

## Provisioning and identity-linking policies

Provisioning and identity linking are deny-by-default.

A successful OIDC or SAML callback only creates or links a local user when one of the following is true:

1. the host application binds its own `ProvisioningPolicy` or `IdentityLinkPolicy`
2. package-wide defaults are enabled in `config/sso.php`
3. the connection explicitly opts in through `sso_connections.settings`

### Package-wide defaults

~~~php
'provisioning' => [
    'enabled_by_default' => false,
],

'linking' => [
    'enabled_by_default' => false,
],
~~~

Set either value to `true` only when that behavior is acceptable for your application.

### Per-connection overrides

Connection settings take precedence over package defaults.

~~~php
$connection->settings = [
    'allow_provisioning' => true,
    'allow_identity_linking' => true,
];
~~~

This makes it possible to allow automatic provisioning or linking for one identity provider while denying it for another within the same tenant.

## IdP URL security

OIDC and SAML identity-provider URLs are trusted only when they are production-safe by default.

The package rejects IdP URLs that are malformed, use embedded credentials, target localhost/private/reserved IP destinations, or use plain HTTP. This applies to OIDC discovery URLs, OIDC authorization/token/JWKS/userinfo endpoints, SAML SSO URLs, and SAML metadata URLs.

For local IdP development only, the defaults can be relaxed explicitly:

~~~php
'security' => [
    'allow_insecure_idp_urls' => false,
    'allow_private_idp_urls' => false,
],
~~~

Do not enable these overrides in production unless equivalent upstream network controls are in place.

## Callback redirect safety

Post-login `redirect_to` values are constrained to safe local paths or same-origin absolute URLs.

The package rejects protocol-relative redirects such as `//evil.example`, malformed URLs, backslash-containing redirects, control characters, and external origins. Unsafe targets fall back to `/`.

## OIDC hardening

OIDC login uses authorization code flow with PKCE S256 and nonce validation. ID token validation requires RS256 signatures, expected issuer, expected audience, expiry, nonce matching, strict key ID matching, and `azp` when a token has multiple audiences.

The package also validates `nbf` and future `iat` claims when present. You may optionally bound accepted ID token age:

~~~php
'oidc' => [
    'id_token' => [
        'max_age_seconds' => env('SSO_OIDC_ID_TOKEN_MAX_AGE_SECONDS'),
    ],
],
~~~

The transient PKCE `code_verifier` is encrypted at rest in `sso_auth_attempts`.

## SAML hardening

SAML responses are parsed with external entity loading disabled and must pass XML signature validation against configured signing certificates.

The package rejects ambiguous or unsupported SAML shapes, including multiple assertions, nested assertions, encrypted assertions, duplicate `ID` attributes, and missing signatures. Destination, audience, recipient, and `InResponseTo` correlation checks remain required by default.

## Claims persistence

External identities persist minimized canonical claims by default rather than raw protocol claims.

~~~php
'claims' => [
    'persist_raw' => false,
    'persist_groups' => true,
    'max_group_items' => 100,
],
~~~

Set `persist_raw` to `true` only when your application explicitly needs raw OIDC/SAML claim snapshots and you have appropriate data-retention controls.

## Audit logging

Audit logging is redacted by default.

Successful callback audits retain only concise metadata such as:

- protocol
- tenant, connection, and attempt identifiers
- status and error classification
- a truncated subject hint plus a one-way subject hash
- claim keys and bounded protocol flags such as `userinfo_used` or SAML signature booleans

The package does not store raw OIDC tokens, raw SAML assertions, or full claim payloads in `sso_audit_logs.context` by default.

### Extended audit context

Extended audit context is available only as an explicit opt-in for debugging:

~~~php
'audit' => [
    'extended_context' => false,
],
~~~

When enabled, the package stores additional redacted summaries for canonical claims and driver context. Sensitive values such as access tokens, ID tokens, refresh tokens, private keys, SAML responses, and raw claim bags remain redacted even in extended mode.

## Auth attempt lifecycle

Callback state handling is replay-safe and uses a two-phase lifecycle.

An auth attempt is reserved for validation before protocol-specific callback validation starts. It is marked consumed only after OIDC or SAML validation succeeds. Failed protocol validation releases the attempt back to pending and records `failed_at`, allowing the caller to retry with a valid callback while still rejecting already consumed attempts.

## Request throttling

Public SSO endpoints are rate limited by default.

The package applies independent throttle buckets to:

- `sso.redirect`
- `sso.callback`
- `sso.acs`

Default limits are intentionally conservative:

~~~php
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
~~~

Disable throttling only if you have strong compensating controls upstream.

## Data retention

Run `php artisan sso:prune --attempts-days=7 --audit-days=30` on a schedule (for example, daily) to remove stale `sso_auth_attempts` and `sso_audit_logs` records and keep tables compact.

Before deploying changes that encrypt transient attempt data, prune stale auth attempts so no expired plaintext attempts remain active during the upgrade window.

## Optional Admin UI

The admin UI is optional and disabled by default.

Enable it in your config:

~~~php
// config/sso.php
'ui' => [
    'enabled' => true,
],
~~~

The UI routes are protected by your configured middleware and gate ability. Admin FormRequests also honor the configured gate when it is defined, as defense in depth.

## Publish UI assets

~~~bash
php artisan vendor:publish --tag="sso-ui"
~~~

## Testing

Run the full test suite:

~~~bash
composer test
~~~

Run the release-quality command set locally:

~~~bash
composer ci
~~~

For maintainer release workflow and release gates, see `docs/maintainer-release-checklist.md`.

## Upgrade guidance

Before adopting milestone changes, review:

- `docs/upgrade-guide.md`
- `CHANGELOG.md`

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
