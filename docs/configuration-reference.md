# Configuration Reference

This document describes `config/sso.php`, environment variables, public routes, and identity-provider configuration shapes. It is the authoritative integrator reference for package settings.

## Publishing configuration

```bash
php artisan sso:install
# or
php artisan vendor:publish --tag=laravel-sso-config
```

## Package enablement

| Key / env | Default | Description |
|-----------|---------|-------------|
| `enabled` / `SSO_ENABLED` | `true` | Master switch. When `false`, the service provider skips route registration and rate limiters. |
| `routes.enabled` / `SSO_ROUTES_ENABLED` | `true` | Register public SSO routes (`redirect`, `callback`, `acs`, `metadata`). |
| `routes.prefix` / `SSO_ROUTE_PREFIX` | `sso` | URL prefix for public SSO routes. |
| `routes.middleware` | `['web']` | Middleware group applied to public SSO routes. |

## Public SSO routes

When `routes.prefix` is `sso`, paths are:

| Route name | Method | Path | Purpose |
|------------|--------|------|---------|
| `sso.redirect` | GET | `/sso/{tenant}/{connection}/redirect` | Begin login; redirects to IdP |
| `sso.oidc.callback` | GET | `/sso/{tenant}/{connection}/callback` | OIDC authorization callback |
| `sso.saml.acs` | POST | `/sso/{tenant}/{connection}/acs` | SAML assertion consumer |
| `sso.saml.metadata` | GET | `/sso/{tenant}/{connection}/metadata` | SP metadata XML |

Route parameters:

- `{tenant}` — tenant **ULID** (`sso_tenants.ulid`)
- `{connection}` — connection **ULID** (recommended) or numeric ID (backward compatibility)

Optional query parameter on `redirect`:

- `redirect_to` — post-login redirect; validated at storage and callback time (see [Security Guide](security.md))

Helper:

```php
sso_redirect_url(string $tenantUlid, int|string $connectionKey): string
```

## Admin API

| Key / env | Default | Description |
|-----------|---------|-------------|
| `ui.enabled` / `SSO_UI_ENABLED` | `false` | Register JSON admin API routes. |
| `ui.prefix` / `SSO_UI_PREFIX` | `admin/sso` | Admin API URL prefix. |
| `ui.middleware` | `['web', 'auth']` | Middleware before the gate check. |
| `ui.gate` / `SSO_UI_GATE` | `manageSso` | Gate ability required for admin API access. |
| `ui.allow_missing_gate` / `SSO_UI_ALLOW_MISSING_GATE` | `false` | Fail closed when the gate is undefined. |

See [Admin API](admin-api.md) for endpoints and examples.

## Tenancy

| Key / env | Default | Description |
|-----------|---------|-------------|
| `tenancy.route_param` | `tenant` | Route parameter name for tenant ULID resolution. |
| `tenancy.default_tenant_ulid` / `SSO_DEFAULT_TENANT_ULID` | `null` | Fallback tenant when no other resolver matches. |
| `tenancy.throw_if_missing` / `SSO_TENANCY_THROW_IF_MISSING` | `true` | Throw when no tenant can be resolved. |
| `tenancy.header.enabled` / `SSO_TENANCY_HEADER_ENABLED` | `false` | Resolve tenant from HTTP header. |
| `tenancy.header.name` / `SSO_TENANCY_HEADER_NAME` | `X-SSO-Tenant` | Header name (tenant ULID value). |
| `tenancy.host.enabled` / `SSO_TENANCY_HOST_ENABLED` | `false` | Resolve tenant from request host/subdomain. |
| `tenancy.host.mode` / `SSO_TENANCY_HOST_MODE` | `host` | `host` or `subdomain`. |
| `tenancy.host.base_domain` / `SSO_TENANCY_BASE_DOMAIN` | `null` | Base domain for subdomain mode. |

Resolver order (first match wins):

1. Route parameter ULID
2. Header (when enabled)
3. Host/subdomain (when enabled)
4. Default tenant ULID

See [Integration Guide](integration-guide.md#tenancy).

## Auth guards

| Key / env | Default | Description |
|-----------|---------|-------------|
| `guards.default` / `SSO_DEFAULT_GUARD` | `null` | Override `auth.defaults.guard` when `connection.guard` is null. |
| `guards.allowed` / `SSO_ALLOWED_GUARDS` | `[]` | Comma-separated allowlist; empty allows all configured guards. |

Per-connection `guard` on `sso_connections.guard` takes precedence.

## Drivers

| Key | Default | Description |
|-----|---------|-------------|
| `drivers.oidc` | `OidcDriver` | OIDC protocol driver class. |
| `drivers.saml` | `SamlDriver` | SAML 2.0 protocol driver class. |

Register custom drivers by adding entries and implementing `SsoDriver`.

## Auth attempts

| Key / env | Default | Description |
|-----------|---------|-------------|
| `attempts.ttl_seconds` / `SSO_ATTEMPT_TTL_SECONDS` | `600` | Attempt expiry (seconds). |
| `attempts.state_length` / `SSO_STATE_LENGTH` | `64` | OAuth/OIDC state length. |
| `attempts.nonce_length` / `SSO_NONCE_LENGTH` | `64` | OIDC nonce length. |
| `attempts.code_verifier_length` / `SSO_CODE_VERIFIER_LENGTH` | `96` | PKCE verifier length. |
| `attempts.validation_lock_ttl_seconds` / `SSO_ATTEMPT_VALIDATION_LOCK_TTL_SECONDS` | `120` | Stale validation lock recovery window. |

PKCE `code_verifier` values are encrypted at rest on `sso_auth_attempts`.

See [Auth Attempt Lifecycle](auth-attempt-lifecycle.md).

## Provisioning

| Key / env | Default | Description |
|-----------|---------|-------------|
| `provisioning.enabled_by_default` / `SSO_PROVISIONING_ENABLED` | `false` | Package-wide JIT provisioning default. |
| `provisioning.require_email_verified` / `SSO_PROVISIONING_REQUIRE_EMAIL_VERIFIED` | `true` | Require verified email before provisioning. |
| `provisioning.trust_saml_email_attributes` / `SSO_PROVISIONING_TRUST_SAML_EMAIL` | `false` | Treat SAML mail attributes as verified. |
| `provisioning.connection_setting_key` / `SSO_PROVISIONING_CONNECTION_SETTING_KEY` | `allow_provisioning` | Connection `settings` override key. |
| `provisioning.email_column` / `SSO_USER_EMAIL_COLUMN` | `email` | User model email column. |
| `provisioning.name_column` / `SSO_USER_NAME_COLUMN` | `name` | User model name column. |

## Linking

| Key / env | Default | Description |
|-----------|---------|-------------|
| `linking.enabled_by_default` / `SSO_LINKING_ENABLED` | `false` | Package-wide identity linking default. |
| `linking.require_email_verified` / `SSO_LINKING_REQUIRE_EMAIL_VERIFIED` | `true` | Require verified email before email-based linking. |
| `linking.trust_saml_email_attributes` / `SSO_LINKING_TRUST_SAML_EMAIL` | `false` | Treat SAML mail attributes as verified. |
| `linking.connection_setting_key` / `SSO_LINKING_CONNECTION_SETTING_KEY` | `allow_identity_linking` | Connection `settings` override key. |

## Throttling

Independent rate limiters (enabled by default):

| Limiter | Config key | Default max / decay |
|---------|------------|---------------------|
| `sso.redirect` | `throttling.redirect` | 60 / 1 min |
| `sso.callback` | `sso.callback` | 30 / 1 min |
| `sso.acs` | `throttling.acs` | 30 / 1 min |
| `sso.metadata` | `throttling.metadata` | 60 / 1 min |

Each bucket supports `enabled`, `max_attempts`, and `decay_minutes` with `SSO_THROTTLE_*` env overrides (see `config/sso.php`).

## Audit logging

| Key / env | Default | Description |
|-----------|---------|-------------|
| `audit.extended_context` / `SSO_AUDIT_EXTENDED_CONTEXT` | `false` | Store additional redacted callback summaries. |
| `audit.subject_hash_algo` / `SSO_AUDIT_SUBJECT_HASH_ALGO` | `sha256` | Subject hash algorithm. |
| `audit.subject_hint_length` / `SSO_AUDIT_SUBJECT_HINT_LENGTH` | `12` | Truncated subject hint length. |
| `audit.string_value_max_length` | `80` | Max string length in audit context. |
| `audit.max_claim_keys` | `20` | Max claim keys in summaries. |
| `audit.max_array_items` | `20` | Max array items in extended context. |

## Security (IdP URL policy)

| Key / env | Default | Description |
|-----------|---------|-------------|
| `security.allow_insecure_idp_urls` / `SSO_ALLOW_INSECURE_IDP_URLS` | `false` | Allow HTTP IdP URLs (local dev only). |
| `security.allow_private_idp_urls` / `SSO_ALLOW_PRIVATE_IDP_URLS` | `false` | Allow private/reserved DNS answers (local dev only). |

## Claims persistence

| Key / env | Default | Description |
|-----------|---------|-------------|
| `claims.persist_raw` / `SSO_CLAIMS_PERSIST_RAW` | `false` | Persist raw protocol claim bags on external identities. |
| `claims.persist_groups` / `SSO_CLAIMS_PERSIST_GROUPS` | `true` | Persist normalized groups. |
| `claims.max_group_items` / `SSO_CLAIMS_MAX_GROUP_ITEMS` | `100` | Max groups persisted per identity. |
| `claims.encrypt_persisted` / `SSO_CLAIMS_ENCRYPT_PERSISTED` | `true` | Encrypt external identity `claims` at rest. |

## OIDC settings

| Key / env | Description |
|-----------|-------------|
| `oidc.discovery.enabled_default` / `SSO_OIDC_DISCOVERY_ENABLED` | Default discovery behavior when IdP config omits `discovery_enabled`. |
| `oidc.discovery.cache_ttl_seconds` / `SSO_OIDC_DISCOVERY_CACHE_TTL` | Discovery document cache TTL. |
| `oidc.discovery.http_timeout_seconds` | Discovery HTTP timeout. |
| `oidc.callback.http_timeout_seconds` / `SSO_OIDC_CALLBACK_HTTP_TIMEOUT` | Token exchange HTTP timeout. |
| `oidc.userinfo.enabled_default` / `SSO_OIDC_USERINFO_ENABLED` | Default userinfo fetch behavior. |
| `oidc.id_token.clock_skew_seconds` / `SSO_OIDC_CLOCK_SKEW_SECONDS` | JWT clock skew tolerance. |
| `oidc.id_token.max_age_seconds` / `SSO_OIDC_ID_TOKEN_MAX_AGE_SECONDS` | Optional max login age (uses `auth_time`). |
| `oidc.id_token.jwks_cache_ttl_seconds` / `SSO_OIDC_JWKS_CACHE_TTL` | JWKS cache TTL. |
| `oidc.id_token.allowed_algorithms` / `SSO_OIDC_ALLOWED_ALGORITHMS` | Default `RS256`. |

## SAML settings

| Key / env | Description |
|-----------|-------------|
| `saml.clock_skew_seconds` / `SSO_SAML_CLOCK_SKEW_SECONDS` | NotBefore/NotOnOrAfter skew. |
| `saml.persist_raw_saml` / `SSO_SAML_PERSIST_RAW` | Opt-in raw SAML detail in driver context. |
| `saml.assertion_replay_cache_seconds` / `SSO_SAML_ASSERTION_REPLAY_CACHE_SECONDS` | Assertion ID replay cache TTL. |
| `saml.require_destination` / `SSO_SAML_REQUIRE_DESTINATION` | Require response Destination match. |
| `saml.require_audience` / `SSO_SAML_REQUIRE_AUDIENCE` | Require audience match. |
| `saml.require_recipient` / `SSO_SAML_REQUIRE_RECIPIENT` | Require SubjectConfirmation Recipient match. |
| `saml.attribute_mapping` | Default SAML attribute name candidates per canonical field. |
| `saml.sp.entity_id` / `SSO_SAML_SP_ENTITY_ID` | Optional SP entity ID override. |
| `saml.sp.sign_authn_requests` / `SSO_SAML_SP_SIGN_AUTHN_REQUESTS` | Sign AuthnRequests (requires PEM env vars). |
| `saml.sp.signing_private_key_pem` / `SSO_SAML_SP_SIGNING_PRIVATE_KEY_PEM` | SP signing private key. |
| `saml.sp.signing_certificate_pem` / `SSO_SAML_SP_SIGNING_CERTIFICATE_PEM` | SP signing certificate. |

---

## Identity provider configuration (OIDC)

Stored encrypted in `sso_identity_providers.config`.

### Required (one of endpoint strategies)

Provide **either**:

- `endpoints.authorization`, `endpoints.token`, `endpoints.jwks`, **or**
- `issuer` (discovery derived), **or**
- `discovery_url`

Always required:

- `client_id` (string)
- `redirect_uri` (HTTPS URL ending in `/callback`)

### Common optional fields

```json
{
  "client_id": "your-client-id",
  "client_secret": "your-client-secret",
  "redirect_uri": "https://app.example.com/sso/{tenant_ulid}/{connection_ulid}/callback",
  "issuer": "https://idp.example.com",
  "discovery_enabled": true,
  "userinfo_enabled": false,
  "scope": "openid profile email",
  "response_type": "code",
  "endpoints": {
    "authorization": "https://idp.example.com/authorize",
    "token": "https://idp.example.com/token",
    "jwks": "https://idp.example.com/jwks",
    "userinfo": "https://idp.example.com/userinfo"
  }
}
```

Manual endpoints example (discovery disabled):

```json
{
  "client_id": "your-client-id",
  "client_secret": "your-client-secret",
  "redirect_uri": "https://app.example.com/sso/01TENANT/01CONNECTION/callback",
  "discovery_enabled": false,
  "userinfo_enabled": false,
  "endpoints": {
    "authorization": "https://idp.example.com/authorize",
    "token": "https://idp.example.com/token",
    "jwks": "https://idp.example.com/jwks"
  }
}
```

Register the exact `redirect_uri` with your IdP. Use tenant and connection **ULIDs** from the admin API or artisan commands.

---

## Identity provider configuration (SAML)

### Required

```json
{
  "saml_sso_url": "https://idp.example.com/sso/saml",
  "saml_signing_certs_pem": [
    "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----"
  ]
}
```

### Optional

```json
{
  "metadata_url": "https://idp.example.com/metadata",
  "cert_thumbprint": "optional-thumbprint",
  "attribute_mapping": {
    "email": ["mail", "email"]
  }
}
```

Connection-level SAML attribute overrides can also be stored on the IdP `config.attribute_mapping` array.

---

## Connection settings

JSON object on `sso_connections.settings`. Common keys:

```json
{
  "allow_provisioning": true,
  "allow_identity_linking": true,
  "required_link_groups": ["staff"],
  "required_provision_groups": ["employees"]
}
```

Group keys apply when binding `GroupRequiredIdentityLinkPolicy` or `GroupRequiredProvisioningPolicy` (see [Integration Guide](integration-guide.md)).
