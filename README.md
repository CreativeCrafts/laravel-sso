# Multi-tenant SSO (OIDC + SAML 2.0) for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)
[![GitHub CI Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-sso/ci.yml?branch=main&label=ci&style=flat-square)](https://github.com/creativecrafts/laravel-sso/actions/workflows/ci.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-sso.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-sso)

Generic OIDC and SAML 2.0 SSO for Laravel, with multi-tenant support, user provisioning, identity linking, redacted audit logging, replay prevention, and configurable throttling on public SSO endpoints.

## Compatibility

This package supports the runtime constraints declared in `composer.json`:

- PHP `^8.3` (8.3, 8.4, 8.5)
- Laravel / Illuminate `^12.0|^13.0`

CI validates PHP 8.3–8.5 across Laravel 12 and 13. See `composer.json` for authoritative constraints.

## Documentation

| Guide | Description |
|-------|-------------|
| [Getting Started](docs/getting-started.md) | Install → first OIDC login |
| [Configuration Reference](docs/configuration-reference.md) | All config keys, env vars, IdP JSON shapes |
| [Integration Guide](docs/integration-guide.md) | Policies, events, tenancy, guards, extension |
| [Admin API](docs/admin-api.md) | JSON admin API + UI scaffold |
| [Operator Guide](docs/operator-guide.md) | Production operating model |
| [Deployment Guide](docs/deployment-guide.md) | Staging/production checklist |
| [Security Guide](docs/security.md) | Hardening and operational security |
| [Troubleshooting Guide](docs/troubleshooting.md) | Common issues |
| [Error Catalog](docs/error-catalog.md) | Exceptions and HTTP status mapping |
| [Upgrade Guide](docs/upgrade-guide.md) | Migration and rollout notes |
| [Auth Attempt Lifecycle](docs/auth-attempt-lifecycle.md) | Callback state machine |
| [Database Schema](docs/database-schema.md) | Tables, columns, indexes, relationships |
| [Architecture Audit](docs/architecture-audit.md) | Design boundaries and limitations |
| [Maintainer Release Checklist](docs/maintainer-release-checklist.md) | Release gates |
| [Contributor Matrix Testing](docs/contributor-matrix-testing.md) | Local PHP/Laravel matrix |

## Installation

```bash
composer require creativecrafts/laravel-sso
php artisan sso:install --run-migrations
```

`sso:install` publishes config (`sso-config`), migrations (`sso-migrations`), and optional UI assets (`sso-ui`).

Manual publish:

```bash
php artisan vendor:publish --tag=sso-config
php artisan vendor:publish --tag=sso-migrations
php artisan migrate
```

## Database foundation

Published migrations create:

- tenants
- identity providers
- connections
- auth attempts
- external identities
- audit logs

Do not treat the database layer as optional.

## Quick start

```bash
php artisan sso:make-tenant "Acme Corp"
php artisan sso:make-idp {tenant_ulid} "Acme OIDC" --protocol=oidc
php artisan sso:make-connection {tenant_ulid} {idp_id} "Acme Connection"
php artisan sso:doctor --strict
```

Configure IdP credentials (admin API or tinker), then add a login button using **connection ULID**:

```blade
<x-sso-button tenant="{{ $tenantUlid }}" connection="{{ $connectionUlid }}" />
```

Full walkthrough: [Getting Started](docs/getting-started.md).

## Public SSO routes

```text
GET  /sso/{tenant_ulid}/{connection_ulid}/redirect
GET  /sso/{tenant_ulid}/{connection_ulid}/callback    (OIDC)
POST /sso/{tenant_ulid}/{connection_ulid}/acs         (SAML)
GET  /sso/{tenant_ulid}/{connection_ulid}/metadata    (SAML SP)
```

Optional: `?redirect_to=/dashboard` on the redirect route (validated for safety).

Helper: `sso_redirect_url($tenantUlid, $connectionUlid, $redirectTo = null)`.

## Provisioning and identity linking

Provisioning and identity linking are **deny-by-default**. A successful callback creates or links a local user only when:

1. the host binds custom `ProvisioningPolicy` / `IdentityLinkPolicy` implementations, **or**
2. package-wide defaults are enabled in `config/sso.php`, **or**
3. the connection opts in via `sso_connections.settings`

Connection settings override package defaults:

```php
$connection->settings = [
    'allow_provisioning' => true,
    'allow_identity_linking' => true,
];
```

Claim-aware example policies: `GroupRequiredProvisioningPolicy`, `GroupRequiredIdentityLinkPolicy`. See [Integration Guide](docs/integration-guide.md).

## Admin API and UI scaffold

The **JSON admin API** manages tenants, identity providers, and connections. Enable with `SSO_UI_ENABLED=true` and register a `manageSso` gate.

An optional **Inertia UI scaffold** can be published (`sso-ui` tag); full CRUD screens are host-app responsibility.

Details: [Admin API](docs/admin-api.md).

## Security highlights

- IdP URL trust policy (HTTPS, no private hosts by default, DNS-checked outbound calls)
- OIDC: PKCE S256, nonce, RS256 JWKS validation, encrypted PKCE verifiers
- SAML: strict XML shapes, signature validation, optional AuthnRequest signing (fail-closed)
- Auth-attempt row-lock lifecycle with deferred consumption
- Redacted audit logging; encrypted IdP config and external identity claims by default
- Independent rate limiters: `sso.redirect`, `sso.callback`, `sso.acs`, `sso.metadata`
- Safe `redirect_to` validation at storage and callback time

Full checklist: [Security Guide](docs/security.md).

Further reading:

- [Threat Model](laravel-sso-threat-model.md) — STRIDE analysis and integrator checklist
- [Security Best Practices Report](security_best_practices_report.md) — remediation status and operational guidance

## Claims persistence

External identities persist minimized canonical claims by default:

```php
'claims' => [
    'persist_raw' => false,
    'persist_groups' => true,
    'max_group_items' => 100,
    'encrypt_persisted' => true,
],
```

## Auth attempt lifecycle

Callbacks use reserve → validate → consume (or retryable failure). See [Auth Attempt Lifecycle](docs/auth-attempt-lifecycle.md).

## Data retention

Schedule daily pruning:

```php
Schedule::command('sso:prune --attempts-days=7 --audit-days=30')->daily();
```

## Testing

```bash
composer test
composer ci    # full quality gate
```

## Upgrade guidance

Review [Upgrade Guide](docs/upgrade-guide.md) and [CHANGELOG](CHANGELOG.md) before upgrading.

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security Vulnerabilities

See [SECURITY.md](SECURITY.md) or the GitHub security policy.

## Credits

- [Godspower Oduose](https://github.com/rockblings)
- [All Contributors](../../contributors)

## License

MIT — see [LICENSE.md](LICENSE.md).
