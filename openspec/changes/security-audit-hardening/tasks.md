# Tasks: Security Audit Hardening

## 1. Redirect Hardening

- [ ] Add callback redirect validator for `redirect_to` values.
- [ ] Reject protocol-relative URLs beginning with `//`.
- [ ] Reject backslash-containing redirects.
- [ ] Reject ASCII control characters in redirects.
- [ ] Preserve safe local path redirects.
- [ ] Preserve same-origin absolute redirects.
- [ ] Add Pest coverage for local, same-origin, external, protocol-relative, malformed, and backslash redirects.

## 2. IdP URL Trust Policy

- [ ] Add default security config for insecure/private IdP URL overrides.
- [ ] Create URL trust validation helper or service.
- [ ] Enforce HTTPS by default for OIDC discovery URLs.
- [ ] Enforce HTTPS by default for OIDC manual authorization/token/JWKS/userinfo endpoints.
- [ ] Enforce HTTPS by default for SAML SSO URLs.
- [ ] Reject embedded URL credentials.
- [ ] Reject loopback, link-local, private, multicast, and metadata-service destinations by default.
- [ ] Add runtime enforcement outside the admin UI path.
- [ ] Add admin FormRequest validation for all endpoint URLs.
- [ ] Add tests for accepted production URLs and rejected unsafe URLs.
- [ ] Add tests for explicit development override behavior.

## 3. OIDC ID Token Validation

- [ ] Require `azp` when `aud` contains multiple values.
- [ ] Require `azp` to equal configured `client_id` when present for multi-audience tokens.
- [ ] Validate `nbf` when present.
- [ ] Validate future `iat` outside skew.
- [ ] Add optional `max_age_seconds` support for old `iat` values.
- [ ] Fail closed when JWT header declares `kid` and JWKS has no matching key.
- [ ] Add tests for `azp`, `nbf`, `iat`, `max_age_seconds`, and unknown `kid` behavior.

## 4. Auth Attempt Lifecycle

- [ ] Decide whether to implement full lifecycle statuses or document current fail-closed behavior.
- [ ] If implementing lifecycle statuses, add migration for `status`, `validating_at`, and failure tracking fields.
- [ ] Add repository/service transitions for `pending`, `validating`, `consumed`, and `failed`.
- [ ] Preserve row locking for callback state handling.
- [ ] Ensure replayed consumed attempts are rejected.
- [ ] Ensure stale validating attempts cannot block login indefinitely.
- [ ] Add tests for valid callback, invalid callback, retry policy, replay, expired state, and concurrent callback attempts.

## 5. PKCE Verifier Storage

- [ ] Encrypt `sso_auth_attempts.code_verifier` at the model/cast layer.
- [ ] Document upgrade guidance for pruning short-lived existing plaintext attempts before deploy.
- [ ] Add tests proving the stored database value is not plaintext.
- [ ] Add tests proving callback token exchange still receives the decrypted verifier.

## 6. SAML XML Shape and Wrapping Resistance

- [ ] Reject SAML responses without exactly one top-level `samlp:Response`.
- [ ] Reject plaintext SAML responses without exactly one `saml:Assertion`.
- [ ] Reject encrypted assertions until explicitly supported.
- [ ] Reject duplicate `ID` attributes across the SAML document.
- [ ] Bind claim extraction to the validated signed assertion or validated signed response.
- [ ] Reject nested assertions and sibling assertions.
- [ ] Preserve existing `InResponseTo`, `Destination`, `Audience`, and `Recipient` checks.
- [ ] Add malicious SAML wrapping regression fixtures.
- [ ] Add tests for duplicate IDs, multiple assertions, unsigned sibling assertions, signed assertion tampering, signed response tampering, and missing conditions.

## 7. Claims Persistence Policy

- [ ] Add claims persistence config with secure defaults.
- [ ] Persist canonical claims by default.
- [ ] Do not persist raw SAML attributes by default.
- [ ] Do not persist raw OIDC claims by default.
- [ ] Add configurable group persistence and maximum group count.
- [ ] Add tests for default minimized persistence.
- [ ] Add tests for explicit raw-claims opt-in.

## 8. Admin Defense in Depth

- [ ] Add FormRequest authorization defense in depth using the configured gate where practical.
- [ ] Keep route-level `can:<gate>` middleware.
- [ ] Add tests confirming unauthorized admin requests are denied.
- [ ] Add tests confirming authorized admin requests still pass.

## 9. Auth Attempt Request Metadata

- [ ] Populate `ip` when creating auth attempts.
- [ ] Populate `user_agent` when creating auth attempts.
- [ ] Bound or truncate user-agent values before persistence.
- [ ] Add tests for metadata persistence.

## 10. Documentation and Tooling Cleanup

- [ ] Align README compatibility text with Composer constraints.
- [ ] Decide whether Laravel 13 support is official or provisional.
- [ ] If Laravel 13 is official, add CI coverage for it.
- [ ] Remove placeholder PHPStan exclusions.
- [ ] Consider enabling `reportUnmatchedIgnoredErrors` once baseline noise is resolved.
- [ ] Update operator documentation for unsafe URL overrides, claim persistence, PKCE pruning, and SAML strictness.

## 11. Release Readiness

- [ ] Run `composer validate --strict`.
- [ ] Run `composer audit --no-interaction`.
- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `vendor/bin/phpstan analyse`.
- [ ] Run `vendor/bin/pest`.
- [ ] Run `vendor/bin/pest --coverage --coverage-text`.
- [ ] Confirm changelog/release notes document all security-relevant behavior changes.