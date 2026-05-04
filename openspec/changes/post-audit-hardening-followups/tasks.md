# Tasks: Post-Audit Hardening Follow-ups

## 1. Upgrade Safety

- [x] Add package upgrade migration for auth-attempt lifecycle fields.
- [x] Add `status` column when missing.
- [x] Add `validating_at` column when missing.
- [x] Add `failed_at` column when missing.
- [x] Add or preserve indexes for lifecycle query paths where portable.
- [x] Backfill consumed attempts to `status = consumed`.
- [x] Backfill non-consumed attempts to `status = pending`.
- [x] Ensure new-install migration stub and upgrade migration remain schema-compatible.
- [x] Document pruning stale auth attempts before encrypted PKCE verifier rollout.
- [x] Add tests for upgrading from old auth-attempt schema.
- [x] Add tests for backfill behavior.

## 2. Auth-Attempt Validation Lock Recovery

- [x] Add `sso.attempts.validation_lock_ttl_seconds` config.
- [x] Reject fresh `validating` attempts as already in progress.
- [x] Recover stale `validating` attempts under row lock.
- [x] Preserve consumed-attempt replay rejection.
- [x] Preserve expired-attempt rejection.
- [x] Add tests for fresh validating lock rejection.
- [x] Add tests for stale validating lock recovery.
- [x] Add tests for consumed and expired stale-lock edge cases.

## 3. DNS-Aware IdP URL Safety

- [x] Add `HostnameResolver` contract.
- [x] Add default DNS resolver implementation.
- [x] Add outbound IdP URL policy that validates URL syntax and resolved IP addresses.
- [x] Reject hostnames that resolve to loopback addresses.
- [x] Reject hostnames that resolve to private RFC1918 addresses.
- [x] Reject hostnames that resolve to link-local, multicast, reserved, or metadata-service addresses.
- [x] Reject mixed public/private DNS answer sets.
- [x] Reject unresolved hostnames by default.
- [x] Disable redirects for IdP HTTP calls or revalidate every redirect target.
- [x] Apply outbound policy to OIDC discovery calls.
- [x] Apply outbound policy to OIDC JWKS fetches.
- [x] Apply outbound policy to OIDC token exchange calls.
- [x] Apply outbound policy to OIDC userinfo calls.
- [x] Add tests using a fake resolver for public, private, loopback, IPv6, mixed-answer, and unresolved hostnames.

## 4. SAML Signature Provenance and Claims Binding

- [x] Extend `SamlSignedXml` with validated response/assertion IDs.
- [x] Record response ID when response signature validates.
- [x] Record assertion ID when assertion signature validates.
- [x] Change SAML claims normalizer to consume validated `SamlSignedXml` instead of raw XML.
- [x] Change SAML assertion extractor to operate on the validated DOM/signature context.
- [x] Ensure signed assertion extraction uses the signed assertion ID.
- [x] Ensure signed response extraction uses the assertion directly under the signed response.
- [x] Ensure condition validation operates on trusted signed context.
- [x] Add tests for signed assertion provenance.
- [x] Add tests for signed response provenance.
- [x] Add malicious wrapping tests proving unsigned sibling claims are not extracted.
- [x] Add regression tests proving raw XML is not reparsed in the driver claims path.

## 5. Documentation Integrity and Upgrade Docs

- [x] Add `docs/upgrade-guide.md`.
- [x] Add or verify all README-linked docs exist.
- [x] Document lifecycle-field upgrade migration.
- [x] Document encrypted PKCE verifier rollout and auth-attempt pruning.
- [x] Document IdP URL trust policy and local-development overrides.
- [x] Document SAML strictness and unsupported encrypted assertions.
- [x] Document claims persistence defaults and raw-claims opt-in.
- [x] Document supported PHP/Laravel CI matrix.
- [x] Add docs-link integrity test or CI script for local README links.

## 6. Admin Validation Design Cleanup

- [x] Replace `app(UrlTrustPolicy::class)` usage in admin validation trait.
- [x] Add explicit validation rule or validator service for trusted IdP URLs.
- [x] Add explicit validator service for OIDC/SAML IdP config structures if needed.
- [x] Preserve existing validation behavior and error messages where practical.
- [x] Add tests for admin validation through the new explicit dependency seam.

## 7. Public API and Lifecycle Semantics

- [x] Decide whether `STATUS_FAILED` should be used, renamed, documented, or removed.
- [x] Document retryable failure semantics when `failed_at` is set and status returns to `pending`.
- [x] Add PHPDoc warning to `AuthAttemptService::consumeByState()`.
- [x] Add implementation PHPDoc warning to `DbAuthAttemptService::consumeByState()`.
- [x] Consider deprecating `consumeByState()` for next major if external use is not required.
- [x] Add tests or documentation for valid public lifecycle transition usage.

## 8. OIDC Temporal Claim Strictness

- [ ] Reject present-but-non-numeric `iat`.
- [ ] Reject present-but-non-numeric `nbf`.
- [ ] Decide whether present-but-non-numeric `auth_time` should fail even when max-age is disabled.
- [ ] Add tests for malformed `iat`.
- [ ] Add tests for malformed `nbf`.
- [ ] Add tests for malformed `auth_time` based on the chosen behavior.

## 9. Redirect Edge-Case Coverage

- [ ] Add tests for encoded protocol-relative paths such as `/%2F%2Fevil.example`.
- [ ] Add tests for encoded backslash paths such as `/%5Cevil.example`.
- [ ] Add tests for literal backslash local paths.
- [ ] Add tests for encoded or decoded control-character redirect values.
- [ ] Decide whether encoded protocol-relative paths are allowed as literal local paths or rejected.
- [ ] Document redirect edge-case decisions through tests.

## 10. Contributor DX for Laravel Matrix

- [ ] Add local Laravel 12 dependency-set testing guidance.
- [ ] Add local Laravel 13 dependency-set testing guidance.
- [ ] Decide whether to add Composer scripts for matrix testing or keep guidance in docs only.
- [ ] Document that CI is the source of truth for PHP/Laravel matrix support.

## 11. Release Readiness

- [ ] Run `composer validate --strict`.
- [ ] Run `composer audit --no-interaction`.
- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `vendor/bin/phpstan analyse`.
- [ ] Run `vendor/bin/pest`.
- [ ] Run `vendor/bin/pest --coverage --coverage-text`.
- [ ] Confirm CI matrix passes PHP 8.3, 8.4, and 8.5 with Laravel 12 and 13.
- [ ] Update release notes/changelog for all upgrade and security-relevant behavior changes.