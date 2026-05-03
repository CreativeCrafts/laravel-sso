# Security Audit Hardening

## Why

A comprehensive audit of the current `main` branch identified no confirmed critical authentication bypass, but it did identify several security-hardening gaps that should be resolved before the next release milestone. The most urgent issue is an open redirect vector through protocol-relative `redirect_to` values. Additional findings cover OIDC trust boundaries, OIDC ID token validation completeness, SAML XML wrapping resistance, auth-attempt lifecycle hardening, sensitive transient secret storage, claims data minimization, admin configuration validation, audit metadata, documentation drift, and static-analysis hygiene.

This change proposal defines a consolidated hardening milestone so the fixes can be implemented as coherent, testable work rather than scattered patches.

## What Changes

Implement a security-hardening pass across the SSO package that:

- Blocks protocol-relative and malformed callback redirects.
- Introduces explicit URL trust validation for OIDC and SAML IdP configuration.
- Strengthens OIDC ID token validation for multi-audience tokens, token temporal claims, and key selection.
- Encrypts transient OIDC PKCE verifier storage.
- Revisits callback auth-attempt consumption semantics to reduce state-burning denial-of-login risk while preserving replay prevention.
- Hardens SAML XML document-shape validation against wrapping and ambiguous assertion extraction.
- Makes external identity claims persistence safer and configurable.
- Improves admin FormRequest defense-in-depth and validates security-sensitive config fields earlier.
- Captures request metadata on auth attempts for incident response.
- Resolves documentation and static-analysis configuration drift.
- Adds regression tests for all audit findings.

## Audit Findings Covered

### High

- Open redirect through protocol-relative `redirect_to` values such as `//evil.example`.

### Medium

- Callback state is consumed before protocol validation, allowing a valid state to be burned by an invalid callback request.
- OIDC discovery, token, JWKS, and userinfo URLs are not constrained to trusted HTTPS destinations.
- OIDC ID token validation does not enforce `azp` for multiple audiences and does not validate `nbf` or bounded `iat`.
- OIDC JWKS `kid` fallback is permissive when a token declares a key ID that does not exist in JWKS.
- OIDC PKCE `code_verifier` is stored in plaintext in `sso_auth_attempts`.
- SAML XML handling should enforce exact response/assertion shape and bind claim extraction to the signed assertion or signed response.
- External identity claims persistence can store more raw protocol data than necessary.
- Admin IdP configuration validation is too permissive for URLs and certificates.

### Low

- README compatibility guidance and Composer constraints disagree on Laravel 13 support.
- Auth attempts have `ip` and `user_agent` columns but currently do not populate them.
- PHPStan config includes placeholder-looking exclusions.
- Admin FormRequests rely entirely on route middleware for authorization.
- Additional uniqueness constraints and operator-facing docs should be reviewed for production safety.

## Non-Goals

- Do not replace the existing OIDC or SAML driver architecture.
- Do not add social-login or OAuth-only flows.
- Do not introduce a new database schema versioning system beyond normal Laravel migrations.
- Do not implement encrypted SAML assertions in this change; explicitly reject unsupported encrypted assertions until they are designed separately.
- Do not weaken existing deny-by-default provisioning and identity-linking behavior.

## Expected Outcome

After implementation, the package should have stronger default security posture, clearer operator controls, and regression coverage for the audit findings. The public SSO flow should remain backward compatible except where unsafe configurations or unsafe redirect targets are rejected by design.

## Release Notes Draft

- Hardened callback redirect validation to block protocol-relative and malformed redirect targets.
- Added stricter IdP endpoint and certificate validation.
- Strengthened OIDC ID token validation and JWKS key selection.
- Hardened SAML response/assertion structure validation.
- Reduced persistence of sensitive transient and raw claim data.
- Added regression tests for audit findings.