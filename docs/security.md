# Security Guide

This document summarizes security-sensitive behavior in `creativecrafts/laravel-sso` and the operational controls operators must configure correctly.

## Secure defaults

- Provisioning and identity linking are **deny-by-default**.
- OIDC requires verified email claims before provisioning or linking unless explicitly relaxed.
- IdP configuration (including `client_secret`) is stored with Laravel's `encrypted:array` cast.
- Persisted external-identity claims are encrypted by default (`SSO_CLAIMS_ENCRYPT_PERSISTED=true`).
- PKCE `code_verifier` values are encrypted on auth attempts.
- Outbound IdP HTTP calls disable redirects and validate URL schemes, hosts, and DNS resolution.
- Public SSO endpoints are rate-limited independently (redirect, callback, ACS, metadata).
- Disabled connections and IdPs return **404** on begin-login routes.
- Unsafe `redirect_to` values are rejected at storage and callback time.

## Public route identifiers

Use **tenant ULIDs** and **connection ULIDs** in public SSO URLs:

```text
/sso/{tenant_ulid}/{connection_ulid}/redirect
/sso/{tenant_ulid}/{connection_ulid}/callback
/sso/{tenant_ulid}/{connection_ulid}/acs
/sso/{tenant_ulid}/{connection_ulid}/metadata
```

Numeric connection IDs remain supported for backward compatibility but are not recommended in externally published URLs because they are enumerable.

Admin API responses include both internal numeric `id` and public `ulid` fields. Prefer `ulid` in route paths.

## HTTP status mapping (public SSO routes)

The package renders empty-body responses for common SSO failures:

| Condition | HTTP status |
|-----------|-------------|
| Auth attempt replay / validation in progress / SAML assertion replay | 409 Conflict |
| Auth attempt expired | 410 Gone |
| Provisioning denied / linking denied / email verification required | 403 Forbidden |
| Disabled or missing connection/IdP | 404 Not Found |

See [Error Catalog](error-catalog.md) and [Integration Guide](integration-guide.md#http-exception-rendering).

## SAML-specific controls

### Email attribute trust

Do **not** enable these unless the IdP strongly attests email ownership:

```env
SSO_PROVISIONING_TRUST_SAML_EMAIL=false
SSO_LINKING_TRUST_SAML_EMAIL=false
```

When enabled, SAML `mail` attributes satisfy email-verification requirements without an `email_verified` claim.

### AuthnRequest signing

When `SSO_SAML_SP_SIGN_AUTHN_REQUESTS=true`, both PEM env vars must be configured:

```env
SSO_SAML_SP_SIGNING_PRIVATE_KEY_PEM=...
SSO_SAML_SP_SIGNING_CERTIFICATE_PEM=...
```

If signing is enabled but keys are missing, the package **fails closed** and does not send unsigned requests.

### ACS POST binding and CSRF

SAML HTTP-POST responses cannot include Laravel CSRF tokens. Trust is established through signed assertions, `InResponseTo` binding, audience/destination validation, and auth-attempt state — not CSRF middleware.

## Claim-aware authorization

The bundled `DefaultIdentityLinkPolicy` and `DefaultProvisioningPolicy` inspect **connection settings only**. They intentionally ignore IdP claims.

When linking or provisioning must depend on groups or roles, bind custom policies such as:

- `CreativeCrafts\LaravelSso\Policies\GroupRequiredIdentityLinkPolicy`
- `CreativeCrafts\LaravelSso\Policies\GroupRequiredProvisioningPolicy`

Example connection settings:

```json
{
  "allow_identity_linking": true,
  "required_link_groups": ["staff", "employees"]
}
```

```json
{
  "allow_provisioning": true,
  "required_provision_groups": ["employees"]
}
```

See [Integration Guide](integration-guide.md#provisioning-and-linking-policies).

## Redirect safety

The optional `redirect_to` query parameter is validated when login begins and again before redirecting after callback. Unsafe values are discarded (stored as `null`, resolved to `/`).

Rejected patterns include external origins, protocol-relative URLs, backslashes, and control characters.

## DNS resolution and SSRF

Outbound IdP URL validation resolves hostnames at request time. DNS TTL changes can alter resolved addresses afterward (TOCTOU). The package disables HTTP redirects on discovery/JWKS/token calls to reduce follow-up request risk.

Never enable these in production:

```env
SSO_ALLOW_INSECURE_IDP_URLS=false
SSO_ALLOW_PRIVATE_IDP_URLS=false
```

## Admin authorization

Register a `manageSso` gate (or override `SSO_UI_GATE`). Keep:

```env
SSO_UI_ALLOW_MISSING_GATE=false
```

Admin routes always require authentication and the configured gate middleware.

## Data at rest

| Data | Protection |
|------|------------|
| IdP `config` | Laravel encrypted cast |
| External identity `claims` | Configurable encryption (default on) |
| Auth attempt `code_verifier` | Laravel encrypted cast |
| Audit logs | Redacted summaries by default |

Disable claim encryption only during migration troubleshooting:

```env
SSO_CLAIMS_ENCRYPT_PERSISTED=false
```

See [Upgrade Guide](upgrade-guide.md) when upgrading existing installations with plaintext claim rows.

## Dependency hygiene

Run regularly:

```bash
composer audit
```

The package CI pipeline includes dependency auditing.

## Related documentation

- [Configuration Reference](configuration-reference.md)
- [Deployment Guide](deployment-guide.md)
- [Auth Attempt Lifecycle](auth-attempt-lifecycle.md)
