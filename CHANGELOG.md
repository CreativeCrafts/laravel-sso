# Changelog

All notable changes to `creativecrafts/laravel-sso` will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning once stable release tags begin.

## [Unreleased]

### Added

- Multi-tenant database foundation for:
  - tenants
  - identity providers
  - connections
  - auth attempts
  - external identities
  - audit logs
- Protocol-agnostic begin-login and handle-callback orchestration
- OIDC support with:
  - discovery and endpoint resolution
  - PKCE S256
  - nonce and state validation
  - JWKS-based ID token validation
  - optional userinfo integration
- SAML 2.0 support with:
  - metadata parsing
  - SP metadata generation
  - assertion extraction
  - signature validation
  - assertion condition validation
  - attribute normalization into canonical claims
- Canonical claims DTO and cross-protocol normalization pipeline
- Auth-attempt lifecycle protections for:
  - replay prevention
  - expiry handling
  - single-use consumption
- Provisioning and identity-linking pipeline with deny-by-default policy behavior
- Multi-guard login support through guard selection
- Redacted-by-default audit logging with bounded extended-context support
- Callback lifecycle and provisioning lifecycle events
- Configurable throttling for redirect, callback, and ACS endpoints
- Optional admin HTTP/API surface
- Operator documentation:
  - operator guide
  - deployment guide
  - troubleshooting guide
  - error catalog
  - architecture audit
  - upgrade guide
  - maintainer release checklist

### Changed

- Provisioning and identity linking now require explicit opt-in by package configuration, connection settings, or host-application policy bindings
- Audit logging now stores redacted summaries rather than raw protocol payloads or unbounded claim bags
- Public authentication endpoints now use independent named rate limiters
- Core callback and provisioning paths now delegate persistence concerns through repository boundaries where appropriate
- Tenant resolution now supports repository-backed route, header, host, and subdomain resolution

### Security

- Deny-by-default provisioning and linking posture
- Replay protection on callback/auth-attempt lifecycle
- Redaction-by-default audit behavior for OIDC and SAML callback context
- Independent throttle buckets for public authentication endpoints
- Safer tenant-bound repository usage across callback and provisioning flows

### Tests

- Expanded end-to-end OIDC and SAML feature coverage
- Added replay-prevention and throttling coverage
- Added repository and tenancy resolver regression coverage
- Added lifecycle-event verification for callback, provisioning, linking, and login completion

### Documentation

- Expanded README with operational guidance and compatibility information
- Added upgrade guidance for milestone changes
- Added maintainer-facing release checklist and release gate