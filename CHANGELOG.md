# Changelog

All notable changes to `creativecrafts/laravel-sso` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2026-07-02

### Fixed

- `sso:make-tenant` performs a case-insensitive ULID existence check before insert, matching admin API validation.

## [1.0.2](https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.2) - 2026-07-02

### Fixed

- Admin tenant ULID validation is case-insensitive via `UniqueTenantUlid`, preventing duplicate tenants that differ only by casing.
- Tenant updates ignore the resolved tenant row when validating ULID changes, so lowercase route keys with matching payload ULIDs no longer return 422.
- `sso:make-tenant` normalizes ULID-shaped values to uppercase on create.

## [1.0.1](https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.1) - 2026-07-02

Documentation and correctness patch for v1.0.0 integrators.

### Fixed

- OIDC `allowed_algorithms` guard now stays aligned with RS256-only JWKS verification (rejects non-RS256 even when listed in config).
- Tenant ULID lookups are case-insensitive for ULID-shaped route keys; admin API normalizes caller-supplied ULIDs to uppercase on write.

### Documentation

- Correct SAML getting-started IdP config keys (`saml_sso_url`, `saml_signing_certs_pem`).
- Fix HTTP status mappings (`OidcCallbackErrorResponse`, `GuardSelectionFailed`) in error catalog and integration guide.
- Fix `ProvisioningPolicy` contract namespace in integration guide example.
- Clarify auth-attempt consumption timing in lifecycle docs.
- Fix throttling config key typo; expand audit env var documentation.
- Add secondary index notes to database schema reference.

## [1.0.0](https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.0) - 2026-07-01

First stable release.

### Added

- Multi-tenant SSO with OIDC and SAML 2.0 drivers
- Database schema: tenants, identity providers, connections, auth attempts, external identities, audit logs
- Public routes: redirect, OIDC callback, SAML ACS, SAML metadata (tenant + connection ULIDs)
- JSON admin API for tenant / IdP / connection CRUD (optional, gate-protected)
- Optional UI scaffold publish tag (`sso-ui`)
- Auth-attempt lifecycle with row-lock validation, deferred consumption, replay protection
- OIDC: PKCE S256, nonce, JWKS validation, discovery, optional userinfo
- SAML: signature validation, condition checks, assertion replay guard, SP metadata, optional AuthnRequest signing
- Deny-by-default provisioning and identity linking with pluggable policies
- Example claim-aware policies: `GroupRequiredIdentityLinkPolicy`, `GroupRequiredProvisioningPolicy`
- Multi-guard support with optional allowlist
- Redacted audit logging with optional extended context
- Encrypted IdP config, encrypted PKCE verifiers, encrypted external identity claims (default)
- Outbound IdP URL and DNS safety policy with redirect disabled on OIDC HTTP calls
- Safe `redirect_to` validation at storage and callback time
- Independent rate limiters: redirect, callback, ACS, metadata
- Tenancy resolvers: route ULID, header, host/subdomain, default tenant
- Lifecycle and provisioning events
- Artisan commands: `sso:install`, `sso:doctor`, `sso:make-tenant`, `sso:make-idp`, `sso:make-connection`, `sso:prune`
- Blade `<x-sso-button>` component and `sso_redirect_url()` helper
- Comprehensive documentation set under `docs/`

### Security

- Fail-closed SAML AuthnRequest signing when enabled without keys
- Disabled SSO resources return 404 on begin-login
- Atomic SAML assertion replay cache (`cache->add`)
- Provisioning race routes through linking policy on email unique constraint
- Admin API config redaction in JSON responses

### Documentation

- Getting Started, Configuration Reference, Integration Guide, Admin API
- Operator, Deployment, Security, Troubleshooting, Error Catalog guides
- Upgrade Guide, Auth Attempt Lifecycle, Architecture Audit
- Maintainer Release Checklist, Contributor Matrix Testing

[1.0.3]: https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.3
[1.0.2]: https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.2
[1.0.1]: https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.1
[1.0.0]: https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.0
