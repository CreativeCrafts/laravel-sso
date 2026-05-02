# Design: Security Audit Hardening

## Context

The current package already has a solid security baseline: tenant-scoped repositories, encrypted IdP config, deny-by-default provisioning/linking policies, replay-preventing auth attempts, throttled public endpoints, redacted audit logging, OIDC PKCE/nonce validation, SAML XML signature validation, and SAML audience/recipient/destination checks.

The audit findings are mostly hardening gaps where existing behavior should become stricter, more explicit, and more testable.

## Design Goals

- Preserve existing package architecture and dependency-injection seams.
- Fail closed for unsafe redirect targets, unsafe IdP URLs, invalid OIDC tokens, ambiguous SAML XML, and unsupported protocol constructs.
- Keep production defaults secure while allowing explicit development escape hatches.
- Minimize stored sensitive data.
- Add focused tests for every security decision.
- Avoid breaking safe existing deployments unnecessarily.

## Proposed Components

### 1. Redirect Target Validation

Introduce a small internal redirect validator, either as a private method on `HandlesCallbackResponse` or as a dedicated service if reuse is expected.

Rules:

- Empty values resolve to `/`.
- Local paths are allowed only when they start with exactly one `/`.
- Protocol-relative paths beginning with `//` are rejected.
- Backslashes are rejected.
- ASCII control characters are rejected.
- Absolute URLs are allowed only when same-origin with the current request.
- Invalid URLs resolve to `/`.

Rationale: this fixes the high-severity open redirect while preserving the existing same-origin absolute URL behavior.

### 2. IdP URL Trust Validation

Add an internal URL policy service, for example `Contracts/Core/UrlTrustPolicy` and `Core/DefaultUrlTrustPolicy`, or keep this as a validation helper if no runtime reuse is needed.

Default policy:

- Require `https` for OIDC discovery, authorization, token, JWKS, userinfo, and SAML SSO URLs.
- Reject loopback, link-local, private ranges, multicast, and known metadata-service addresses.
- Reject URLs with embedded credentials.
- Reject non-HTTP(S) schemes.
- Reject malformed hosts.

Development override:

```php
'security' => [
    'allow_insecure_idp_urls' => env('SSO_ALLOW_INSECURE_IDP_URLS', false),
    'allow_private_idp_urls' => env('SSO_ALLOW_PRIVATE_IDP_URLS', false),
],
```

The override must be explicit and documented as unsafe for production.

Apply this policy in:

- Admin IdP store/update FormRequests.
- Runtime OIDC discovery/endpoint resolution.
- Runtime SAML AuthnRequest start.

Runtime enforcement is required because IdP records may be created outside the admin UI.

### 3. OIDC ID Token Validation

Extend `DefaultOidcIdTokenValidator`.

New checks:

- When `aud` is an array with more than one value, require `azp` and require `azp === client_id`.
- If `nbf` exists, reject tokens not valid yet outside configured skew.
- If `iat` exists, reject future tokens outside skew.
- Add optional maximum token age setting:

```php
'oidc' => [
    'id_token' => [
        'max_age_seconds' => env('SSO_OIDC_ID_TOKEN_MAX_AGE_SECONDS', null),
    ],
],
```

- If a JWT header includes `kid`, only that key may be used. If no matching key exists, fail closed.
- Continue supporting RS256 initially; other algorithms require a separate design.

### 4. Auth Attempt Lifecycle

The current model marks attempts consumed before protocol driver validation. That is fail-closed for replay but allows a valid state to be burned by an invalid callback.

Preferred design:

- Add a nullable `validating_at` timestamp or `status` column in a new migration.
- Transition `pending -> validating` under lock when a callback begins.
- Transition `validating -> consumed` only after driver validation succeeds.
- Transition `validating -> failed` or clear `validating_at` after failed validation, subject to bounded retry rules.
- Keep replay prevention by rejecting already consumed attempts and rejecting stale validating locks.

Lower-impact alternative:

- Keep current consumption timing.
- Add explicit documentation and an audit event for state-burn failures.
- Treat the behavior as an intentional fail-closed tradeoff.

Recommendation: implement the preferred design if the package has not yet promised a stable schema. Use the lower-impact alternative only for patch releases.

### 5. Encrypt Transient OIDC Secrets

Encrypt `sso_auth_attempts.code_verifier`.

Options:

- Change the model cast to `encrypted` for `code_verifier`.
- Or introduce a custom encrypted cast if compatibility/migration handling is required.

Recommendation:

- Use Laravel's `encrypted` cast for new installations.
- Provide an upgrade note for existing plaintext attempts; because attempts are short-lived, operators can prune expired attempts before deploying.

### 6. SAML XML Shape and Wrapping Resistance

Extend SAML validation with strict document constraints.

Rules:

- Require exactly one top-level `samlp:Response`.
- Require exactly one `saml:Assertion` for supported plaintext assertions.
- Reject encrypted assertions until explicitly supported.
- Reject duplicate `ID` attributes across the document.
- When assertion signature is validated, extract claims only from that validated assertion.
- When response signature is validated, extract claims only from the assertion inside the validated response.
- Reject nested assertions or sibling assertions.
- Keep `Destination`, `Audience`, `Recipient`, and `InResponseTo` checks required by default.

Implementation shape:

- Expand `SamlSignedXml` DTO to carry the validated response element and/or validated assertion element IDs.
- Have `DefaultSamlSignatureValidator` return enough structure for downstream extraction to bind claims to the validated element.
- Update `SamlAssertionExtractor` to accept the validated XML context instead of reparsing the raw XML independently.

### 7. Claims Persistence Policy

Introduce a configurable claims persistence policy.

Default behavior:

- Persist canonical claims only: subject hash or subject, email, display name, email verification, and groups if enabled.
- Do not persist raw SAML attribute bags by default.
- Do not persist raw OIDC claim bags by default.

Suggested config:

```php
'claims' => [
    'persist_raw' => env('SSO_CLAIMS_PERSIST_RAW', false),
    'persist_groups' => env('SSO_CLAIMS_PERSIST_GROUPS', true),
    'max_group_items' => env('SSO_CLAIMS_MAX_GROUP_ITEMS', 100),
],
```

The audit sanitizer remains separate; this change concerns `sso_external_identities.claims`.

### 8. Admin Request Authorization Defense in Depth

Keep route-level `can:<gate>` middleware.

Add optional FormRequest-level authorization using the configured gate where practical. Because FormRequest instances may be used in tests or outside the intended route group, this creates another guardrail.

### 9. Auth Attempt Metadata

Populate existing `ip` and `user_agent` columns during attempt creation.

Implementation option:

- Add optional `RequestContext` DTO to `AuthAttemptService::create()`.
- Or pass `ip` and `user_agent` explicitly from `BeginLoginService`.

Recommendation: pass a small DTO to avoid growing the method signature further.

### 10. Documentation and Tooling Hygiene

- Align README compatibility with Composer constraints and CI coverage.
- If Laravel 13 support remains in Composer, add CI matrix coverage for Laravel 13 or mark it provisional.
- Remove placeholder PHPStan exclusions.
- Consider enabling `reportUnmatchedIgnoredErrors` once the baseline is stable.

## Migration Strategy

Potential schema changes:

- `sso_auth_attempts.code_verifier` encryption does not require a schema change if column type remains text.
- Auth-attempt lifecycle hardening may require `status`, `validating_at`, and/or `failed_at` columns.

Backwards compatibility:

- Existing active plaintext attempts may fail after enabling encrypted casts. This is acceptable if release notes instruct operators to prune auth attempts before deploy.
- Unsafe IdP URLs may start failing validation/runtime checks. This is intentional for production safety and should be documented with development escape hatches.

## Security Considerations

- URL trust policy must account for DNS rebinding where possible. If URL hostnames are allowed, runtime HTTP clients should still avoid resolved private IPs. Full DNS-level enforcement may need a dedicated HTTP client wrapper.
- SAML wrapping defenses must be tested with malicious documents, not just happy-path signed fixtures.
- Raw claims can contain PII and authorization data; default persistence must minimize data.
- State lifecycle changes must not reintroduce replay attacks.

## Rollout Plan

1. Ship redirect validator first as a patch-safe fix.
2. Add URL trust policy and validation tests.
3. Harden OIDC validator and JWKS key selection.
4. Encrypt PKCE verifier and document pruning requirement.
5. Harden SAML shape validation and extraction binding.
6. Add claims persistence controls.
7. Add metadata capture and documentation/tooling cleanup.

## Open Questions

- Should auth-attempt lifecycle use a simple `status` enum or separate timestamps?
- Should development insecure/private URL overrides be package-level only, or per-connection?
- Should raw claim persistence be fully disabled by default or retained behind an explicit per-connection flag?
- Should Laravel 13 support be removed until CI validates it?