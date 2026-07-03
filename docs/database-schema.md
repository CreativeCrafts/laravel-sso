# Database Schema

Published migration: `database/migrations/create_sso_tables.php.stub` (tag `sso-migrations`).

All tenant-scoped tables cascade-delete when a tenant is removed.

## Entity relationships

```text
sso_tenants
  ├── sso_identity_providers
  │     └── sso_connections
  ├── sso_auth_attempts
  ├── sso_external_identities
  └── sso_audit_logs
```

## `sso_tenants`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Internal numeric id |
| `ulid` | ulid, unique | Public tenant identifier in SSO URLs |
| `name` | string, nullable | Display name |
| `metadata` | json, nullable | Host/subdomain tenancy keys (`domain`, `domains`, `subdomain`) |
| `created_at`, `updated_at` | timestamps | |

## `sso_identity_providers`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Used in admin nested routes |
| `tenant_id` | FK → `sso_tenants` | Tenant scope |
| `ulid` | ulid, unique | Public IdP identifier |
| `name` | string | Display name |
| `protocol` | string | `oidc` or `saml` |
| `enabled` | boolean | Default `true`; disabled IdPs return 404 on login |
| `config` | text, nullable | Encrypted JSON (OIDC/SAML endpoints, credentials) |
| `created_at`, `updated_at` | timestamps | |

Index: `(tenant_id, enabled)`.

## `sso_connections`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Backward-compatible route key |
| `tenant_id` | FK → `sso_tenants` | Tenant scope |
| `identity_provider_id` | FK → `sso_identity_providers` | Parent IdP |
| `ulid` | ulid, unique | **Recommended** public connection key in SSO URLs |
| `name` | string | Display name |
| `enabled` | boolean | Default `true` |
| `guard` | string, nullable | Laravel guard for login (falls back to config defaults) |
| `settings` | json, nullable | Per-connection overrides (`allow_provisioning`, `allow_identity_linking`, group keys) |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(tenant_id, enabled)`, `(tenant_id, identity_provider_id)`.

## `sso_auth_attempts`

Short-lived OIDC/SAML callback state — not durable login history.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `tenant_id` | FK → `sso_tenants` | Tenant scope |
| `identity_provider_id` | FK, nullable | Bound at login start |
| `connection_id` | FK, nullable | Bound at login start |
| `protocol` | string | `oidc` or `saml` |
| `state` | string | OAuth `state` or SAML `RelayState` |
| `nonce` | string, nullable | OIDC nonce |
| `code_verifier` | text, nullable | Encrypted PKCE verifier |
| `redirect_to` | text, nullable | Validated post-login redirect |
| `expires_at` | timestamp | Attempt TTL |
| `consumed_at` | timestamp, nullable | Set when login completes or provisioning fails terminally |
| `status` | string(32) | `pending`, `validating`, `consumed`, `failed` |
| `validating_at` | timestamp, nullable | Validation lock timestamp |
| `failed_at` | timestamp, nullable | Last retryable validation failure |
| `ip`, `user_agent` | nullable | Request metadata |
| `context` | json, nullable | Additional redacted context |
| `created_at`, `updated_at` | timestamps | |

Unique: `(tenant_id, state)`.

Additional indexes: `(tenant_id, expires_at)`, `(tenant_id, consumed_at)`, `(tenant_id, status)`, `(tenant_id, validating_at)`.

See [Auth Attempt Lifecycle](auth-attempt-lifecycle.md).

## `sso_external_identities`

Maps IdP subjects to local authenticatable models.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `tenant_id` | FK → `sso_tenants` | Tenant scope |
| `identity_provider_id` | FK → `sso_identity_providers` | IdP scope |
| `provider_subject` | string | IdP `sub` or SAML NameID |
| `email` | string, nullable | Denormalized email |
| `display_name` | string, nullable | Denormalized display name |
| `authenticatable_type` | string | Eloquent morph type |
| `authenticatable_id` | string | Eloquent morph id |
| `claims` | longText, nullable | Encrypted minimized claims by default |
| `created_at`, `updated_at` | timestamps | |

Unique: `(tenant_id, identity_provider_id, provider_subject)`.

Indexes: `(tenant_id, email)`, `(authenticatable_type, authenticatable_id)`.

## `sso_audit_logs`

Redacted operational audit trail.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `tenant_id` | FK → `sso_tenants` | Tenant scope |
| `identity_provider_id` | FK, nullable | |
| `connection_id` | FK, nullable | |
| `auth_attempt_id` | FK, nullable | |
| `event` | string | e.g. `sso.callback.succeeded`, `sso.callback.failed` |
| `level` | string, nullable | e.g. `info`, `warning` |
| `context` | json, nullable | Redacted summary (extended context opt-in) |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(tenant_id, created_at)`, `(event)`.

Prune with `php artisan sso:prune --audit-days=30`.

## Related documentation

- [Configuration Reference](configuration-reference.md)
- [Integration Guide](integration-guide.md)
- [Auth Attempt Lifecycle](auth-attempt-lifecycle.md)
- [Error Catalog](error-catalog.md)
