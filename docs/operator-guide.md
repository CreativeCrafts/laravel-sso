# Operator Guide

This guide describes the production operating model for `creativecrafts/laravel-sso`.

## Production baseline

Before enabling SSO for a tenant, verify:

- package migrations are published and migrated (`php artisan sso:install --run-migrations`)
- `APP_KEY` is stable and backed up (required for encrypted IdP config and PKCE verifiers)
- public routes are protected by the package throttle configuration (four independent buckets)
- tenant, identity-provider, and connection records exist with **ULIDs** recorded for IdP registration
- OIDC/SAML callback URLs use tenant and connection **ULIDs** and are registered with the identity provider
- the `manageSso` gate is registered when the admin API is enabled
- pruning is scheduled for stale auth attempts and audit logs

Run:

```bash
php artisan sso:doctor --strict
```

Treat **blocking issues** (printed as errors) as deployment blockers. **Warnings** (printed in yellow) are informational — review them, but they may be acceptable in local development (for example, unset SAML SP entity ID when SAML is unused).

## Tenant and connection model

A tenant owns identity providers and connections. A connection is the runtime unit used by login routes and can override provisioning, linking, guard, and protocol settings.

Recommended operating rules:

1. Use one connection per IdP app registration.
2. Keep production and staging IdP registrations separate.
3. Prefer explicit connection settings over package-wide policy changes.
4. Publish **connection ULIDs** (not numeric IDs) in IdP redirect and ACS URLs.
5. Disable or remove stale connections rather than reusing them for unrelated IdPs.

## Tenancy operations

Default resolution uses the tenant ULID in the URL path. Optional header and host modes are available — see [Integration Guide](integration-guide.md#tenancy).

Ensure `sso_tenants.metadata` is populated when using host/subdomain resolution.

## OIDC operations

OIDC uses authorization code flow with PKCE S256 and nonce validation.

Production OIDC connections should define:

- issuer or discovery URL (or manual HTTPS endpoints)
- client ID and secret (when required)
- redirect URI using tenant and connection ULIDs
- final HTTPS endpoints when discovery redirects are not desired

Outbound IdP HTTP calls validate URL safety and resolved DNS answers immediately before the request. Redirect following is disabled for discovery, JWKS, token exchange, and userinfo calls.

## SAML operations

SAML responses must be signed and must pass destination, audience, recipient, and correlation checks by default.

Production SAML connections should define:

- IdP SSO URL
- signing certificates in PEM format (`saml_signing_certs_pem`)
- current and next signing certificate during IdP certificate rotation
- ACS URL and SP entity ID registered with the IdP using connection ULIDs

Unsupported or ambiguous SAML shapes are rejected, including encrypted assertions, multiple assertions, nested assertions, duplicate IDs, and missing signatures.

When AuthnRequest signing is enabled, both SP signing PEM env vars must be configured or begin-login fails closed.

## Provisioning and linking

Provisioning and identity linking are denied by default. Enable them only when the host application has a reviewed user-lifecycle policy.

Use connection-level settings to scope automatic behavior to one IdP connection:

```php
[
    'allow_provisioning' => true,
    'allow_identity_linking' => true,
]
```

When authorization must depend on IdP groups or roles, bind claim-aware policies — see [Security Guide](security.md#claim-aware-authorization) and [Integration Guide](integration-guide.md#provisioning-and-linking-policies).

## Audit and retention

Audit context is redacted by default. Raw tokens and raw SAML responses are not persisted by the package.

Schedule pruning daily:

```php
// routes/console.php
Schedule::command('sso:prune --attempts-days=7 --audit-days=30')->daily();
```

Or via cron invoking `php artisan sso:prune --attempts-days=7 --audit-days=30`.

Choose retention values that match your incident-response and privacy requirements.

## Upgrade operations

Before upgrading production applications, read [Upgrade Guide](upgrade-guide.md) and apply any migration, pruning, or rollout steps before deploying the new package version.

## Related documentation

- [Deployment Guide](deployment-guide.md) — pre-deploy checklist and smoke tests
- [Configuration Reference](configuration-reference.md) — all settings and IdP JSON shapes
- [Admin API](admin-api.md) — programmatic tenant/IdP/connection management
- [Auth Attempt Lifecycle](auth-attempt-lifecycle.md) — callback replay semantics
- [Error Catalog](error-catalog.md) — failure modes and operator actions
