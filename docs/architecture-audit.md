# Architecture Audit

This document summarizes the current architecture and the hardening expectations that protect the package's public guarantees.

## Architectural boundaries

The package is organized around these boundaries:

- `Contracts`: public extension seams for host applications and tests
- `Core`: orchestration, tenancy, policy, lifecycle, and helper services
- `Drivers`: protocol-specific OIDC and SAML driver implementations
- `Protocol`: protocol parsing, validation, normalization, and metadata utilities
- `Repositories`: persistence adapters for package models
- `Models`: Eloquent representations of package tables
- `Http`: route controllers, form requests, and admin API

Host applications should customize behavior through contracts and configuration rather than by depending on concrete internal classes. See [Integration Guide](integration-guide.md).

## Correctness posture

The package uses explicit lifecycle transitions for auth attempts:

1. create pending attempt
2. reserve for validation (row lock)
3. validate protocol callback
4. mark consumed on success
5. release to pending with `failed_at` on retryable validation failure

This preserves replay protection while avoiding consumption before protocol validation succeeds.

Details: [Auth Attempt Lifecycle](auth-attempt-lifecycle.md).

## Security posture

Security defaults are conservative:

- provisioning is denied by default
- identity linking is denied by default
- audit context is redacted by default
- raw claims are not persisted by default
- external identity claims are encrypted by default
- IdP URLs must be production-safe by default
- public routes use tenant and connection ULIDs (numeric connection IDs supported for backward compatibility)
- OIDC callbacks require nonce, PKCE, issuer, audience, expiry, signature, and key checks
- SAML callbacks require strict document shape and XML signature validation
- disabled resources return 404 on begin-login
- `redirect_to` is validated at storage and callback time

Details: [Security Guide](security.md).

## OIDC design

OIDC support is split into endpoint resolution, discovery, JWKS fetching, ID-token validation, claim normalization, and callback orchestration.

Outbound IdP HTTP requests pass through URL and DNS-aware safety checks immediately before request dispatch. Redirect following is disabled for discovery, JWKS, token exchange, and userinfo calls.

## SAML design

SAML support is split into metadata parsing, SP metadata generation, signature validation, condition validation, assertion extraction, claim mapping, replay guarding, and callback orchestration.

Claims extraction and condition validation consume `SamlSignedXml`, which carries validated response/assertion provenance. This prevents later code from reparsing raw XML and drifting away from the signed context.

AuthnRequest signing fails closed when enabled but PEM keys are missing.

## Persistence design

Package tables are tenant-scoped where applicable. Auth attempts are short-lived protocol state, not durable login history. Audit logs are bounded and redacted by default.

Public identifiers:

- tenants: ULID
- connections and identity providers: ULID (plus internal numeric IDs)

Upgrade migrations bridge older published schemas because host applications may have copied migration stubs.

## Public API design

Contracts define the supported extension points. Public method signatures should be treated as backward-compatibility commitments. DTOs should remain predictable value objects and should avoid mutable shared state where practical.

Admin API returns redacted IdP config in JSON responses; secrets remain encrypted at rest.

## Operational design

Operators should rely on:

- `php artisan sso:doctor` for configuration and environment checks
- scheduled `sso:prune` for retention
- documentation index starting at [Getting Started](getting-started.md)
- CI matrix for PHP/Laravel compatibility confidence ([Contributor Matrix Testing](contributor-matrix-testing.md))

## Known limitations

- OIDC ID token validation supports algorithms listed in `sso.oidc.id_token.allowed_algorithms` (default RS256).
- SAML encrypted assertions are rejected rather than decrypted.
- SAML document shapes are intentionally strict.
- Private or insecure IdP URLs require explicit local-development overrides.
- Admin UI is a scaffold; management is via JSON admin API or host-app tooling.

These limitations are intentional safety boundaries unless changed by a future design proposal.

## Documentation map

| Audience | Start here |
|----------|------------|
| New integrators | [Getting Started](getting-started.md) |
| Settings reference | [Configuration Reference](configuration-reference.md) |
| Extension / events | [Integration Guide](integration-guide.md) |
| Production ops | [Operator Guide](operator-guide.md), [Deployment Guide](deployment-guide.md) |
| Incidents | [Troubleshooting Guide](troubleshooting.md), [Error Catalog](error-catalog.md) |
