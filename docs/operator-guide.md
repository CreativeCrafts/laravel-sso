# Operator Guide

This guide describes the production operating model for `creativecrafts/laravel-sso`.

## Production baseline

Before enabling SSO for a tenant, verify:

- package migrations are published and migrated
- `APP_KEY` is stable and backed up
- public routes are protected by the package throttle configuration
- tenant, identity-provider, and connection records exist
- OIDC/SAML callback URLs are registered with the identity provider
- pruning is scheduled for stale auth attempts and audit logs

Run:

```bash
php artisan sso:doctor
```

Treat doctor warnings as deployment blockers unless they are explicitly accepted for a local-development environment.

## Tenant and connection model

A tenant owns identity providers and connections. A connection is the runtime unit used by login routes and can override provisioning, linking, guard, and protocol settings.

Recommended operating rules:

1. Use one connection per IdP app registration.
2. Keep production and staging IdP registrations separate.
3. Prefer explicit connection settings over package-wide policy changes.
4. Disable or remove stale connections rather than reusing them for unrelated IdPs.

## OIDC operations

OIDC uses authorization code flow with PKCE S256 and nonce validation.

Production OIDC connections should define:

- issuer or discovery URL
- client ID
- redirect URI
- client secret when required by the provider
- final HTTPS endpoints when discovery redirects are not desired

Outbound IdP HTTP calls validate URL safety and resolved DNS answers immediately before the request. Configure final HTTPS endpoints directly; redirect following is disabled for discovery, JWKS, token exchange, and userinfo calls.

## SAML operations

SAML responses must be signed and must pass destination, audience, recipient, and correlation checks by default.

Production SAML connections should define:

- IdP SSO URL
- IdP entity ID where applicable
- signing certificates in PEM format
- current and next signing certificate during IdP certificate rotation

Unsupported or ambiguous SAML shapes are rejected, including encrypted assertions, multiple assertions, nested assertions, duplicate IDs, and missing signatures.

## Provisioning and linking

Provisioning and identity linking are denied by default. Enable them only when the host application has a reviewed user-lifecycle policy.

Use connection-level settings to scope automatic behavior to one IdP connection:

```php
[
    'allow_provisioning' => true,
    'allow_identity_linking' => true,
]
```

## Audit and retention

Audit context is redacted by default. Raw tokens and raw SAML responses are not persisted by the package.

Schedule pruning:

```bash
php artisan sso:prune --attempts-days=7 --audit-days=30
```

Choose retention values that match your incident-response and privacy requirements.

## Upgrade operations

Before upgrading production applications, read `docs/upgrade-guide.md` and apply any migration, pruning, or rollout steps before deploying the new package version.
