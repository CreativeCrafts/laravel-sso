# Deployment Guide

Use this guide when deploying `creativecrafts/laravel-sso` to staging or production.

## Pre-deploy checklist

1. Confirm the target application runs a supported PHP and Laravel version (see `composer.json`).
2. Run `php artisan sso:install --run-migrations` (or publish `sso-migrations` and migrate).
3. Confirm `APP_KEY` is stable across all app instances.
4. Confirm queue, cache, and session backends are production-ready.
5. Confirm public SSO routes are reachable over HTTPS.
6. Confirm proxy and URL generation settings produce the correct public application origin (`APP_URL`, trusted proxies).
7. Register the `manageSso` gate when `SSO_UI_ENABLED=true`.
8. Schedule `sso:prune` (see [Data retention](#data-retention)).
9. Run `php artisan sso:doctor --strict` in CI or pre-deploy hooks.

## Environment configuration

Minimum production expectations:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
SSO_ENABLED=true
SSO_ROUTES_ENABLED=true
SSO_ALLOW_INSECURE_IDP_URLS=false
SSO_ALLOW_PRIVATE_IDP_URLS=false
SSO_UI_ALLOW_MISSING_GATE=false
SSO_CLAIMS_ENCRYPT_PERSISTED=true
```

Only enable insecure or private IdP URL overrides in local development or tightly controlled test environments.

## Database migrations

```bash
php artisan sso:install --run-migrations
```

Or manually:

```bash
php artisan vendor:publish --tag=sso-migrations
php artisan migrate --force
```

The package publishes three migrations:

1. `create_sso_tables` — base schema
2. `add_lifecycle_fields_to_sso_auth_attempts` — auth-attempt lifecycle columns (existing installs)
3. `add_public_ulids_to_sso_resources` — connection and IdP ULIDs (existing installs)

Existing installations should review [Upgrade Guide](upgrade-guide.md) before deploying a new package version.

## Admin gate registration

When the admin API is enabled:

```php
Gate::define('manageSso', fn ($user) => /* your authorization logic */);
```

See [Admin API](admin-api.md).

## IdP application registration

For each production connection, register URLs using **tenant ULID** and **connection ULID**:

| Purpose | Path |
|---------|------|
| Begin login | `/sso/{tenant_ulid}/{connection_ulid}/redirect` |
| OIDC callback | `/sso/{tenant_ulid}/{connection_ulid}/callback` |
| SAML ACS | `/sso/{tenant_ulid}/{connection_ulid}/acs` |
| SAML metadata | `/sso/{tenant_ulid}/{connection_ulid}/metadata` |

Numeric connection IDs remain supported for backward compatibility but are enumerable — do not publish them externally.

Use the actual public URL from the deployed application (scheme, host, route prefix, ULIDs). Connection ULIDs are returned by artisan commands and the admin API.

See [Security Guide](security.md) for the full security checklist.

## OIDC deployment notes

- Use authorization code flow with PKCE (handled by the package).
- Configure final HTTPS discovery, token, JWKS, and userinfo endpoints when possible.
- Do not rely on IdP endpoint redirects (disabled for outbound calls).
- Ensure DNS for IdP hostnames resolves to public routable addresses from the application network.
- Rotate client secrets through normal secret-management processes.

## SAML deployment notes

- Configure one or more PEM signing certificates on the IdP config.
- Keep old and new signing certificates configured during certificate rollover.
- Confirm the IdP posts to the correct ACS URL (with connection ULID).
- Confirm the SP entity ID matches the IdP audience value.
- Keep destination, audience, recipient, and correlation checks enabled unless you have a specific compatibility requirement and compensating controls.

## Throttling

Public SSO endpoints are throttled by default. Keep throttling enabled unless an upstream gateway provides equivalent protection.

Default buckets:

- `sso.redirect`
- `sso.callback`
- `sso.acs`
- `sso.metadata`

Configure limits in `config/sso.php` under `throttling.*`.

## Data retention

Schedule pruning in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sso:prune --attempts-days=7 --audit-days=30')->daily();
```

Manual run:

```bash
php artisan sso:prune --attempts-days=7 --audit-days=30
```

Tune retention values to your organization's support, audit, and privacy requirements.

Before upgrades involving encrypted transient data, prune or allow existing auth attempts to expire — see [Upgrade Guide](upgrade-guide.md).

## Smoke test

After deployment:

1. Run `php artisan sso:doctor --strict`.
2. Start an OIDC login and complete the callback.
3. Start a SAML login and complete ACS if the application uses SAML.
4. Confirm audit records are redacted.
5. Confirm failed callbacks do not consume auth attempts (retryable pending state).
6. Confirm stale auth attempts are pruned by schedule.

## Related documentation

- [Getting Started](getting-started.md)
- [Configuration Reference](configuration-reference.md)
- [Operator Guide](operator-guide.md)
- [Troubleshooting Guide](troubleshooting.md)
