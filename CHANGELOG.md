# Changelog

All notable changes to `creativecrafts/laravel-sso` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.0.0](https://github.com/creativecrafts/laravel-sso/releases/tag/v1.0.0/compare/v1.0.0...v1.0.0) - 2026-07-02

First stable release of `creativecrafts/laravel-sso`.

### Highlights

- Multi-tenant OIDC and SAML 2.0 SSO for Laravel 12/13 (PHP 8.3–8.5)
- Deny-by-default provisioning and identity linking with pluggable policies
- Auth-attempt lifecycle with deferred consumption, replay protection, and row-lock validation
- JSON admin API, optional UI scaffold, and comprehensive integrator documentation
- Security hardening: PKCE, JWKS validation, SAML signature/conditions checks, SSRF-aware IdP URL policy, encrypted secrets
- **90% test coverage** enforced in CI

### Install

```bash
composer require creativecrafts/laravel-sso
php artisan sso:install --run-migrations

```
### Documentation

- [Getting Started](https://github.com/CreativeCrafts/laravel-sso/blob/main/docs/getting-started.md)
- [Configuration Reference](https://github.com/CreativeCrafts/laravel-sso/blob/main/docs/configuration-reference.md)
- [Integration Guide](https://github.com/CreativeCrafts/laravel-sso/blob/main/docs/integration-guide.md)
- [Security Guide](https://github.com/CreativeCrafts/laravel-sso/blob/main/docs/security.md)

Full changelog: [CHANGELOG.md](https://github.com/CreativeCrafts/laravel-sso/blob/main/CHANGELOG.md)

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
