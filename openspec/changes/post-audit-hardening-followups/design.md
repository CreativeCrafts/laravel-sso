# Design: Post-Audit Hardening Follow-ups

## Context

The merged security-hardening milestone improved the package baseline: safer redirects, stricter OIDC validation, stricter SAML shape validation, encrypted PKCE verifier storage, minimized claims persistence, auth-attempt lifecycle fields, and broader CI coverage.

This follow-up change addresses issues that are less suited to tactical patches because they affect upgrade sequencing, network safety, and long-term API design.

## Design Goals

- Provide a safe upgrade path for existing installations.
- Keep new-install migration stubs and upgrade migrations aligned.
- Strengthen SSRF protections for all outbound IdP HTTP calls.
- Preserve retry and replay-safety semantics for auth attempts.
- Make SAML signed-element trust explicit and impossible to accidentally bypass downstream.
- Improve documentation integrity and contributor experience.
- Avoid unnecessary public API churn where deprecation/documentation is sufficient.

## 1. Upgrade Migrations

### Problem

The new migration stub includes lifecycle fields, but existing installations that already published and ran the previous stub will not receive them automatically.

### Design

Add a new package migration stub, for example:

```php
add_lifecycle_fields_to_sso_auth_attempts.php.stub
```

The migration must be idempotent across common upgrade states:

- Add `status` if missing.
- Add `validating_at` if missing.
- Add `failed_at` if missing.
- Add indexes if missing where feasible.
- Backfill `status`:
  - `consumed_at IS NOT NULL` => `consumed`
  - otherwise => `pending`

Because Laravel schema builders do not consistently support portable index-existence checks across all databases, the implementation may either:

- keep index additions conservative and documented, or
- use connection-driver-aware checks for supported CI databases.

### PKCE verifier rollout

The encrypted cast should remain. Operators should prune short-lived auth attempts before deploying if they have existing plaintext verifier values.

Add documentation that active login attempts may be invalidated during upgrade and should be retried.

### Tests

- Fresh migration still creates all columns.
- Upgrade migration adds missing columns to an old-style schema.
- Backfill maps consumed attempts to `consumed` and non-consumed attempts to `pending`.
- Existing expired plaintext attempts can be pruned before encrypted-cast rollout.

## 2. Stale Auth-Attempt Validation Lock Recovery

### Problem

`pending -> validating` protects callback validation, but a dead request can leave an attempt stuck in `validating` until expiration.

### Design

Add config:

```php
'attempts' => [
    'validation_lock_ttl_seconds' => env('SSO_ATTEMPT_VALIDATION_LOCK_TTL_SECONDS', 120),
],
```

Update reservation behavior:

- If attempt is `consumed`, always reject.
- If attempt is expired, reject.
- If attempt is `validating` and `validating_at` is younger than the TTL, reject with `AuthAttemptValidationInProgress`.
- If attempt is `validating` and `validating_at` is older than the TTL, reset it under row lock and reserve it again.
- If attempt is `pending`, reserve normally.

The reset should preserve `failed_at` or add a separate `last_validation_recovered_at` only if required. Keep schema minimal unless recovery auditability is required.

### Tests

- Young validating attempt is rejected.
- Stale validating attempt is reserved again.
- Consumed stale validating attempt is rejected.
- Expired stale validating attempt is rejected.
- Recovery behavior is row-lock protected.

## 3. DNS-Aware IdP URL Safety

### Problem

String-level URL trust validation blocks unsafe literals, but hostnames can resolve to private/reserved IPs at request time.

### Design

Introduce explicit abstractions:

```php
interface HostnameResolver
{
    /** @return list<string> IP addresses */
    public function resolve(string $hostname): array;
}

interface IdpHttpClient
{
    public function getJson(string $url, int $timeoutSeconds): array;
    public function postForm(string $url, array $form, int $timeoutSeconds): array;
}
```

`DefaultHostnameResolver` should use PHP DNS functions and normalize IPv4/IPv6 results. It must be replaceable in tests.

`UrlTrustPolicy` should gain a method such as:

```php
public function assertTrustedForOutboundRequest(string $url, string $field): void;
```

or a separate `OutboundIdpUrlPolicy` should wrap URL parsing plus DNS resolution.

Rules:

- Keep existing string-level checks.
- Resolve hostname before request unless host is already an IP literal.
- Reject if any resolved IP is private, loopback, link-local, multicast, reserved, or metadata-service-adjacent.
- Reject if resolution returns no addresses.
- Disable redirects where possible, or revalidate every redirect target before following.
- Apply the policy immediately before every outbound IdP request.

### Apply to

- OIDC discovery.
- OIDC JWKS fetch.
- OIDC token exchange.
- OIDC userinfo fetch.
- Future SAML metadata fetches.

### Tests

- Public hostname resolving to public IP is allowed.
- Hostname resolving to `127.0.0.1` is rejected.
- Hostname resolving to RFC1918 private IP is rejected.
- Hostname resolving to IPv6 loopback/link-local is rejected.
- Mixed public/private answers are rejected.
- Redirect targets are rejected or disabled.

## 4. SAML Signature Provenance and Claims Binding

### Problem

The validator returns a validated `DOMDocument`, but claims normalization reparses the original XML string. Current strict shape rules mitigate immediate wrapping risk, but the design does not bind extraction to the signed context explicitly.

### Design

Extend `SamlSignedXml` to carry provenance:

```php
final readonly class SamlSignedXml
{
    public function __construct(
        public DOMDocument $document,
        public bool $validatedResponseSignature,
        public bool $validatedAssertionSignature,
        public ?string $validatedResponseId,
        public ?string $validatedAssertionId,
    ) {}
}
```

Change contracts:

```php
interface SamlClaimsNormalizer
{
    public function normalize(SamlSignedXml $signed): Claims;
}

interface SamlAssertionExtractor
{
    public function extract(SamlSignedXml $signed): array;
}
```

Extraction rules:

- If assertion signature is valid, extract only from the assertion with `validatedAssertionId`.
- If response signature is valid, extract only from the single assertion directly under the signed response with `validatedResponseId`.
- Never reparse raw XML for claims extraction after signature validation.
- Conditions validation should also operate on `SamlSignedXml` and trusted signed context.

### Backward compatibility

These are internal package contracts. If they are documented as extension points, provide a migration note for custom implementations.

### Tests

- Signed assertion extraction uses the signed assertion ID.
- Signed response extraction uses the assertion under the signed response.
- Unsigned sibling assertion is ignored/rejected.
- Reordered or duplicated assertion fixture fails.
- Claims extractor is not passed raw XML in the driver path.

## 5. Documentation Integrity

### Problem

README references docs that may not exist, including upgrade guidance required for this release.

### Design

Add the missing docs or remove links. Required docs:

- `docs/upgrade-guide.md`
- `docs/operator-guide.md`
- `docs/deployment-guide.md`
- `docs/troubleshooting.md`
- `docs/error-catalog.md`
- `docs/maintainer-release-checklist.md`
- `docs/architecture-audit.md`

Minimum required for this change: `upgrade-guide.md`, with explicit notes for:

- lifecycle migration rollout
- PKCE encrypted verifier rollout
- safe auth-attempt pruning
- IdP URL trust policy and development overrides
- SAML strictness changes
- claims persistence changes
- supported PHP/Laravel matrix

Add a docs-link integrity test or CI script that fails when README links local docs that do not exist.

## 6. Admin Validation Dependency Design

### Problem

The FormRequest validation trait calls `app(UrlTrustPolicy::class)`, hiding a dependency and making unit testing less explicit.

### Design

Create a validation rule object:

```php
final readonly class TrustedIdpUrl implements ValidationRule
{
    public function __construct(private UrlTrustPolicy $policy) {}
}
```

Where dynamic nested config validation is still easiest in `withValidator()`, delegate to an injected/configurable validator service instead of calling the container inside the trait.

Target design:

```php
final readonly class IdentityProviderConfigValidator
{
    public function validateOidc(Validator $validator, array $config): void;
    public function validateSaml(Validator $validator, array $config): void;
}
```

FormRequest can resolve the validator through method injection if supported, or through a single contained seam that is easy to override in tests.

## 7. Public API and Lifecycle Semantics

### AuthAttempt statuses

Current implementation defines `STATUS_FAILED`, but failed protocol validation resets status to `pending` and records `failed_at` so the attempt can be retried.

Options:

1. Rename/document as retryable failure metadata and keep status pending.
2. Introduce explicit statuses such as `failed_retryable` and `failed_terminal`.
3. Remove unused `STATUS_FAILED` if there is no code path that uses it.

Recommendation: document current retryable semantics and remove or use `STATUS_FAILED` only if the implementation needs terminal failures.

### consumeByState

`consumeByState()` remains useful for BC, but it can bypass the preferred two-phase validation lifecycle.

Design:

- Add PHPDoc warning to the contract and implementation.
- Prefer `reserveForValidation()` + `markConsumed()` in internal driver flows.
- Consider deprecation in the next major release if not needed externally.

## 8. OIDC Temporal Claim Strictness

### Problem

Malformed `iat` or `nbf` values are ignored when present but non-numeric.

### Design

Fail closed when optional temporal claims are present but invalid type:

- `iat` present and not int/float => reject.
- `nbf` present and not int/float => reject.
- `auth_time` present when max-age disabled may remain unchecked unless configured, or may be type-validated if present for stricter compliance.

Add tests for malformed temporal claims.

## 9. Redirect Edge-Case Coverage

Add tests for encoded redirect edge cases:

- `/%2F%2Fevil.example`
- `/%5Cevil.example`
- `/\\evil.example`
- control-character encodings where Laravel preserves or decodes them

Decide whether encoded protocol-relative paths should be allowed as literal local paths or rejected for defense in depth. Document the decision in tests.

## 10. Contributor DX for Laravel Matrix

Add docs or Composer scripts for local matrix testing.

Possible scripts:

```json
{
  "test:laravel12": "composer require illuminate/contracts:^12.0 illuminate/database:^12.0 illuminate/support:^12.0 orchestra/testbench:^10.11 --update-with-all-dependencies && composer ci",
  "test:laravel13": "composer require illuminate/contracts:^13.0 illuminate/database:^13.0 illuminate/support:^13.0 orchestra/testbench:^11.0 --update-with-all-dependencies && composer ci"
}
```

Alternatively document the commands in `CONTRIBUTING.md` to avoid scripts that mutate the local dependency set unexpectedly.

## Rollout Plan

1. Add upgrade migration and upgrade guide.
2. Add stale validation-lock recovery.
3. Add DNS-aware outbound IdP URL policy and apply it to every IdP HTTP call.
4. Refactor SAML signature provenance and claims extraction binding.
5. Add docs-link validation and missing docs.
6. Clean up admin validation dependency design.
7. Clarify auth-attempt public API semantics.
8. Add remaining regression tests and update release notes.

## Open Questions

- Should stale validating attempts reset to `pending` or a distinct retryable failure status?
- Should DNS-aware SSRF protection reject hostnames with any private answer, or only the selected answer used by the HTTP client?
- Should redirects be completely disabled for OIDC HTTP calls, or followed only after revalidation?
- Are SAML protocol contracts considered public extension points requiring deprecation notices?
- Should local Laravel matrix scripts mutate Composer dependencies, or should CI remain the only matrix source of truth?