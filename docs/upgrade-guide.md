# Upgrade Guide

This guide covers release-sensitive upgrade steps for `creativecrafts/laravel-sso`.

## Related documentation

- [Configuration Reference](configuration-reference.md)
- [Integration Guide](integration-guide.md)
- [Security Guide](security.md)

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

## Public resource ULIDs

Connections and identity providers now expose public `ulid` values for use in SSO and admin route paths.

Existing installations should publish and run migrations:

```bash
php artisan vendor:publish --tag="sso-migrations"
php artisan migrate
```

After migration, prefer connection ULIDs in published SSO URLs:

```text
/sso/{tenant_ulid}/{connection_ulid}/redirect
```

Numeric connection IDs remain supported for backward compatibility.

## Encrypted external identity claims

External identity `claims` are encrypted at rest by default (`SSO_CLAIMS_ENCRYPT_PERSISTED=true`).

Existing plaintext claim rows are read transparently and re-encrypted on the next update. To bulk re-encrypt, touch external identity records in a maintenance task or temporarily disable encryption only during migration troubleshooting:

```env
SSO_CLAIMS_ENCRYPT_PERSISTED=false
```

## Callback validation lifecycle

Callback handling now reserves attempts before protocol validation and consumes them only after successful protocol validation.

Failed protocol validation records `failed_at` and returns the attempt to retryable pending state. Already consumed attempts remain replay-protected and cannot be consumed again.

If a request dies after reserving an attempt but before consuming or releasing it, the attempt can be recovered after the validation lock TTL:

```php
'attempts' => [
    'validation_lock_ttl_seconds' => env('SSO_ATTEMPT_VALIDATION_LOCK_TTL_SECONDS', 120),
],
```

Fresh `validating` attempts are rejected as already in progress. Stale `validating` attempts are recovered under row lock only when they are not consumed and not expired.

## Outbound IdP URL safety

Outbound OIDC HTTP calls now validate both the configured URL string and the resolved DNS answers immediately before each request.

This applies to OIDC discovery, JWKS fetches, token exchange, and userinfo fetches.

By default, hostnames must resolve to globally reachable public IP addresses. Unresolved hosts and hosts with any local, private, link-local, multicast, reserved, documentation, benchmarking, or shared-address DNS answer are rejected. Redirect following is disabled for these outbound IdP HTTP calls, so configure the final HTTPS endpoint directly.

Private-address validation can be relaxed only through the explicit local-development setting:

```php
'security' => [
    'allow_private_idp_urls' => env('SSO_ALLOW_PRIVATE_IDP_URLS', false),
],
```

Keep this setting disabled in production unless equivalent network controls are enforced outside the package.

## SAML strictness

SAML responses are intentionally strict after this hardening milestone.

The package rejects unsupported or ambiguous SAML shapes before accepting signatures, including:

- encrypted assertions
- multiple assertions
- nested assertions
- duplicate `ID` attributes
- missing signatures
- signatures whose `Reference` does not target the signed response or assertion being validated

Encrypted assertions are not decrypted by this package. If your IdP is configured to encrypt assertions, disable assertion encryption for this SP integration or introduce a separate design proposal for decryption support.

Claims extraction, condition validation, and callback correlation use the validated signed context. For assertion-signed responses, response-level `Destination` validation is still read from the single response envelope while claims and recipient/correlation checks are bound to the signed assertion.

## Claims persistence defaults

External identities persist minimized canonical claims by default. Raw OIDC or SAML claim bags can contain personal data and authorization data, so raw persistence remains opt-in:

```php
'claims' => [
    'persist_raw' => env('SSO_CLAIMS_PERSIST_RAW', false),
    'persist_groups' => env('SSO_CLAIMS_PERSIST_GROUPS', true),
    'max_group_items' => env('SSO_CLAIMS_MAX_GROUP_ITEMS', 100),
],
```

Keep `persist_raw` disabled unless your application has a defined retention policy and a reviewed need for raw provider claims.

## Compatibility notes

The package supports the runtime constraints declared in `composer.json`:

- PHP `^8.3`, including PHP 8.3, 8.4, and 8.5
- Laravel / Illuminate `^12.0|^13.0`

CI validates PHP 8.3, 8.4, and 8.5 across Laravel 12 and Laravel 13. CI is the source of truth for supported dependency-set coverage.

## Security-default changes to review

Before upgrading production applications, review these behavior changes:

- OIDC/SAML IdP URLs are trusted only when they are production-safe by default.
- Outbound IdP hostnames must resolve to globally reachable public addresses by default.
- Local insecure/private IdP URL overrides must be enabled explicitly and should not be used in production.
- External identity claims are minimized by default; raw claim persistence requires explicit opt-in.
- SAML responses are rejected when they use unsupported ambiguous shapes such as multiple assertions, nested assertions, duplicate IDs, or encrypted assertions.
- OIDC `max_age_seconds`, when configured, uses `auth_time` login freshness rather than `iat` token issuance time.
