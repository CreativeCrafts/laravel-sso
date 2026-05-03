# Post-Audit Hardening Follow-ups

## Why

A follow-up audit of `main` after the security-audit hardening merge found no obvious critical authentication bypass, but it identified release-readiness gaps and second-order hardening work that should be addressed before tagging the next stable release.

The highest risk is upgrade safety: the package now expects auth-attempt lifecycle columns and encrypted transient PKCE verifier handling, but existing installations that already published and ran the old migration do not receive those schema changes automatically. Additional findings cover DNS-aware SSRF protection, stale callback-validation locks, SAML claims extraction binding, documentation integrity, and public API clarity.

This proposal defines a focused follow-up milestone to convert the audit findings into explicit, testable package behavior.

## What Changes

Implement a post-audit hardening pass that:

- Adds upgrade migrations for existing installations.
- Documents and tests upgrade behavior around auth-attempt lifecycle fields and encrypted PKCE verifier rollout.
- Adds stale validation-lock recovery for auth attempts stuck in `validating`.
- Introduces DNS-resolution-aware outbound IdP URL protection.
- Binds SAML condition and claims extraction to validated signature provenance.
- Clarifies SAML signed-element trust metadata.
- Adds or corrects missing operator/deployment/upgrade documentation referenced by README.
- Replaces service-locator usage in admin validation with explicit validation services or rules.
- Clarifies public API lifecycle semantics around `consumeByState()` and auth-attempt statuses.
- Adds edge-case regression tests for redirects, malformed OIDC temporal claims, SAML extraction binding, and migration upgrades.
- Adds local DX guidance for testing Laravel 12 and Laravel 13 dependency sets.

## Audit Findings Covered

### High

- Existing installations may fail after upgrade because lifecycle columns were added only to the migration stub, while runtime code now writes `status`, `validating_at`, and `failed_at`.
- Existing plaintext active `code_verifier` values may conflict with encrypted casts unless operators prune or migrate attempts during upgrade.

### Medium

- IdP URL trust validation does not resolve hostnames before outbound HTTP calls, leaving DNS rebinding/private-address resolution risk.
- Auth attempts can remain stuck in `validating` if a process dies after reservation but before release/consume.
- SAML signature validation and claims extraction use separate parsing paths; extraction is not directly bound to the validated DOM/signature context.
- SAML signature result metadata does not expose enough signed-element provenance for future-safe extraction.
- README references documentation files that are missing or not guaranteed to exist.

### Low / Design Debt

- Admin IdP validation uses `app()` inside a FormRequest trait instead of explicit dependency seams.
- `AuthAttempt::STATUS_FAILED` exists but failed validation currently resets status to `pending` with `failed_at`, creating ambiguous lifecycle semantics.
- `AuthAttemptService::consumeByState()` remains public and can bypass the preferred two-phase protocol-validation lifecycle if misused.
- OIDC `iat` and `nbf` are ignored when present but malformed/non-numeric.
- Redirect validation lacks regression coverage for encoded slash/backslash/control-character variants.
- Local contributor workflow defaults to the Laravel 13 dev dependency set, while CI mutates dependencies to test Laravel 12 and 13.

## Non-Goals

- Do not redesign the entire OIDC or SAML driver architecture.
- Do not add encrypted SAML assertion support; keep rejecting encrypted assertions until separately designed.
- Do not weaken existing deny-by-default provisioning and identity-linking behavior.
- Do not make insecure/private IdP URL overrides easier to enable in production.
- Do not remove Laravel 12 or Laravel 13 support.
- Do not implement support for non-RS256 OIDC ID token algorithms in this change; document RS256-only behavior unless a separate JOSE design is approved.

## Expected Outcome

After implementation, existing users should have a safe upgrade path, outbound IdP HTTP calls should have stronger SSRF controls, callback validation should recover from abandoned validation locks, SAML claim extraction should be explicitly tied to validated signature provenance, documentation links should be reliable, and package public APIs should more clearly communicate lifecycle constraints.

## Release Notes Draft

- Added upgrade migrations for auth-attempt lifecycle columns and related upgrade guidance.
- Added stale validation-lock handling for callback auth attempts.
- Hardened outbound IdP HTTP calls with DNS-resolution-aware URL safety checks.
- Bound SAML claims extraction to validated SAML signature context.
- Added signed-element provenance metadata to SAML validation results.
- Added missing upgrade/operator documentation and docs-link coverage.
- Clarified auth-attempt lifecycle API semantics and SAML/OIDC validation limitations.