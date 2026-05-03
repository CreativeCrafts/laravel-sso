# Tasks: Security Audit Hardening

## 1. Redirect Hardening

- [x] Add callback redirect validator for `redirect_to` values.
- [x] Reject protocol-relative URLs beginning with `//`.
- [x] Reject backslash-containing redirects.
- [x] Reject ASCII control characters in redirects.
- [x] Preserve safe local path redirects.
- [x] Preserve same-origin absolute redirects.
- [x] Add Pest coverage for local, same-origin, external, protocol-relative, malformed, and backslash redirects.

## 2. IdP URL Trust Policy

- [x] Add default security config for insecure/private IdP URL overrides.
- [x] Create URL trust validation helper or service.
- [x] Enforce HTTPS by default for OIDC discovery URLs.
- [x] Enforce HTTPS by default for OIDC manual authorization/token/JWKS/userinfo endpoints.
- [x] Enforce HTTPS by default for SAML SSO URLs.
- [x] Reject embedded URL credentials.
- [x] Reject loopback, link-local, private, multicast, and metadata-service destinations by default.
- [x] Add runtime enforcement outside the admin UI path.
- [x] Add admin FormRequest validation for all endpoint URLs.
- [x] Add tests for accepted production URLs and rejected unsafe URLs.
- [x] Add tests for explicit development override behavior.

## 3. OIDC ID Token Validation

- [x] Require `azp` when `aud` contains multiple values.
- [x] Require `azp` to equal configured `client_id` when present for multi-audience tokens.
- [x] Validate `nbf` when present.
- [x] Validate future `iat` outside skew.
- [x] Add optional `max_age_seconds` support using `auth_time` freshness.
- [x] Fail closed when JWT header declares `kid` and JWKS has no matching key.
- [x] Add tests for `azp`, `nbf`, `iat`, `max_age_seconds`/`auth_time`, and unknown `kid` behavior.

## 4. Auth Attempt Lifecycle

- [x] Decide whether to implement full lifecycle statuses or document current fail-closed behavior.
- [x] If implementing lifecycle statuses, add migration for `status`, `validating_at`, and failure tracking fields.
- [x] Add repository/service transitions for `pending`, `validating`, `consumed`, and `failed`.
- [x] Preserve row locking for callback state handling.
- [x] Ensure replayed consumed attempts are rejected.
- [x] Ensure stale validating attempts cannot block login indefinitely.
- [x] Add tests for valid callback, invalid callback, retry policy, replay, expired state, and concurrent callback attempts.

## 5. PKCE Verifier Storage

- [x] Encrypt `sso_auth_attempts.code_verifier` at the model/cast layer.
- [x] Document upgrade guidance for pruning short-lived existing plaintext attempts before deploy.
- [x] Add tests proving the stored database value is not plaintext.
- [x] Add tests proving callback token exchange still receives the decrypted verifier.

## 6. SAML XML Shape and Wrapping Resistance

- [x] Reject SAML responses without exactly one top-level `samlp:Response`.
- [x] Reject plaintext SAML responses without exactly one `saml:Assertion`.
- [x] Reject encrypted assertions until explicitly supported.
- [x] Reject duplicate `ID` attributes across the SAML document.
- [x] Bind claim extraction to the validated signed assertion or validated signed response.
- [x] Reject nested assertions and sibling assertions.
- [x] Preserve existing `InResponseTo`, `Destination`, `Audience`, and `Recipient` checks.
- [x] Add malicious SAML wrapping regression fixtures.
- [x] Add tests for duplicate IDs, multiple assertions, unsigned sibling assertions, signed assertion tampering, signed response tampering, and missing conditions.

## 7. Claims Persistence Policy

- [x] Add claims persistence config with secure defaults.
- [x] Persist canonical claims by default.
- [x] Do not persist raw SAML attributes by default.
- [x] Do not persist raw OIDC claims by default.
- [x] Add configurable group persistence and maximum group count.
- [x] Add tests for default minimized persistence.
- [x] Add tests for explicit raw-claims opt-in.

## 8. Admin Defense in Depth

- [x] Add FormRequest authorization defense in depth using the configured gate where practical.
- [x] Keep route-level `can:<gate>` middleware.
- [x] Add tests confirming unauthorized admin requests are denied.
- [x] Add tests confirming authorized admin requests still pass.

## 9. Auth Attempt Request Metadata

- [x] Populate `ip` when creating auth attempts.
- [x] Populate `user_agent` when creating auth attempts.
- [x] Bound or truncate user-agent values before persistence.
- [x] Add tests for metadata persistence.

## 10. Documentation and Tooling Cleanup

- [x] Align README compatibility text with Composer constraints.
- [x] Decide whether Laravel 13 support is official or provisional.
- [ ] If Laravel 13 is official, add CI coverage for it.
- [x] Remove placeholder PHPStan exclusions.
- [ ] Consider enabling `reportUnmatchedIgnoredErrors` once baseline noise is resolved.
- [x] Update operator documentation for unsafe URL overrides, claim persistence, PKCE pruning, and SAML strictness.

## 11. Release Readiness

- [x] Run `composer validate --strict`.
- [x] Run `composer audit --no-interaction`.
- [x] Run `vendor/bin/pint --test`.
- [x] Run `vendor/bin/phpstan analyse`.
- [x] Run `vendor/bin/pest`.
- [x] Run `vendor/bin/pest --coverage --coverage-text`.
- [x] Confirm changelog/release notes document all security-relevant behavior changes.