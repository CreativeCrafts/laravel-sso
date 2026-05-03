# Upgrade Guide

This guide covers release-sensitive upgrade steps for `creativecrafts/laravel-sso`.

## Auth-attempt lifecycle fields

The package now uses explicit auth-attempt lifecycle metadata during callback validation:

- `status`
- `validating_at`
- `failed_at`

New installations receive these columns from the base `create_sso_tables` migration.

Existing installations that already published and ran an older `create_sso_tables` migration receive the columns from the package upgrade migration:

```bash
php artisan vendor:publish --tag="sso-migrations"
php artisan migrate
```

The upgrade migration is additive and safe to run when the base table already exists. It adds missing lifecycle columns and backfills status values:

- attempts with `consumed_at` are marked `consumed`
- attempts without `consumed_at` are marked `pending`

The migration is intentionally non-destructive on rollback because fresh installations own these fields through the base migration.

## PKCE verifier encryption

Transient OIDC PKCE `code_verifier` values are encrypted at the Eloquent cast layer.

If you are upgrading from a version that stored plaintext `code_verifier` values, prune or allow existing auth attempts to expire before deploying the new code. Active login attempts created before the deploy may need to be retried.

Recommended rollout:

1. Wait for the configured auth-attempt TTL to pass, or run the prune command for stale attempts.
2. Deploy the new package version.
3. Publish and run package migrations.
4. Ask users with in-flight SSO logins to retry if they fail during the upgrade window.

Example prune command:

```bash
php artisan sso:prune --attempts-days=0
```

Use a wider retention value if your deployment policy requires retaining recent attempts. Auth attempts are short-lived protocol state and should not be treated as durable login history.

## Callback validation lifecycle

Callback handling now reserves attempts before protocol validation and consumes them only after successful protocol validation.

Failed protocol validation records `failed_at` and returns the attempt to retryable pending state. Already consumed attempts remain replay-protected and cannot be consumed again.

## Compatibility notes

The package supports the runtime constraints declared in `composer.json`:

- PHP `^8.3`, including PHP 8.3, 8.4, and 8.5
- Laravel / Illuminate `^12.0|^13.0`

CI validates PHP 8.3, 8.4, and 8.5 across Laravel 12 and Laravel 13.

## Security-default changes to review

Before upgrading production applications, review these behavior changes:

- OIDC/SAML IdP URLs are trusted only when they are production-safe by default.
- Local insecure/private IdP URL overrides must be enabled explicitly and should not be used in production.
- External identity claims are minimized by default; raw claim persistence requires explicit opt-in.
- SAML responses are rejected when they use unsupported ambiguous shapes such as multiple assertions, nested assertions, duplicate IDs, or encrypted assertions.
- OIDC `max_age_seconds`, when configured, uses `auth_time` login freshness rather than `iat` token issuance time.
