# Integration Guide

This guide explains how host applications extend `creativecrafts/laravel-sso`: policies, events, tenancy, guards, custom drivers, and service-provider bindings.

## Service provider bindings

The package registers default implementations in `LaravelSsoServiceProvider`. Override bindings in your application's `AppServiceProvider` or a dedicated provider **after** the package provider loads:

```php
use CreativeCrafts\LaravelSso\Contracts\Policies\IdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Contracts\Policies\ProvisioningPolicy;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredIdentityLinkPolicy;
use CreativeCrafts\LaravelSso\Policies\GroupRequiredProvisioningPolicy;

public function register(): void
{
    $this->app->singleton(ProvisioningPolicy::class, GroupRequiredProvisioningPolicy::class);
    $this->app->singleton(IdentityLinkPolicy::class, GroupRequiredIdentityLinkPolicy::class);
}
```

### Common contract overrides

| Contract | Default | Use when |
|----------|---------|----------|
| `ProvisioningPolicy` | `DefaultProvisioningPolicy` | Claim-aware or custom onboarding rules |
| `IdentityLinkPolicy` | `DefaultIdentityLinkPolicy` | Claim-aware account linking |
| `UserProvisioner` | `DefaultUserProvisioner` | Custom user creation logic |
| `UserLocator` | `DefaultUserLocator` | Custom email/subject lookup |
| `TenantResolver` | `CompositeTenantResolver` | Replace tenancy strategy entirely |
| `GuardSelector` | `DefaultGuardSelector` | Custom guard selection rules |
| `SsoDriver` (via `sso.drivers`) | OIDC/SAML drivers | Additional protocols |

See `src/Contracts/` for the full public surface.

## Provisioning and linking policies

Defaults are **deny-by-default** and inspect **connection settings only** (not IdP claims).

Enable per connection:

```json
{
  "allow_provisioning": true,
  "allow_identity_linking": true
}
```

Or enable package-wide in `config/sso.php` (see [Configuration Reference](configuration-reference.md)).

### Example: group-required policies

Bind the bundled example policies and configure connection settings:

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

Custom policies receive `$claims` (normalized protocol claims) — implement `IdentityLinkPolicy::allows()` or `ProvisioningPolicy::allows()` accordingly.

## Tenancy

### Route parameter (default)

Public URLs include the tenant ULID:

```text
/sso/{tenant_ulid}/{connection_ulid}/redirect
```

The route parameter resolver loads the tenant from `sso_tenants.ulid`.

### Header-based tenancy

```env
SSO_TENANCY_HEADER_ENABLED=true
SSO_TENANCY_HEADER_NAME=X-SSO-Tenant
```

Send the tenant ULID in the configured header. Useful for API-first apps that omit tenant from the URL path (still recommended for SSO URLs).

### Host / subdomain tenancy

```env
SSO_TENANCY_HOST_ENABLED=true
SSO_TENANCY_HOST_MODE=subdomain
SSO_TENANCY_BASE_DOMAIN=example.com
```

Matches tenant metadata (`sso_tenants.metadata`) against the request host or `{subdomain}.{base_domain}`.

#### Tenant metadata keys

| Key | Type | Used when | Description |
|-----|------|-----------|-------------|
| `domain` | string | `host` mode | Primary hostname (e.g. `acme.example.com`) |
| `domains` | string[] | `host` mode | Additional hostnames that resolve to the tenant |
| `subdomain` | string | `subdomain` mode | Subdomain label (e.g. `acme` for `acme.example.com`) |

In `subdomain` mode, the resolver also accepts the subdomain segment as a tenant ULID when no `metadata.subdomain` match exists.

Example:

```json
{
  "domain": "acme.example.com",
  "domains": ["www.acme.example.com"],
  "subdomain": "acme"
}
```

### Default tenant fallback

```env
SSO_DEFAULT_TENANT_ULID=01HXXXXXXXXXXXXXXXXXXXXXXX
```

Used when no other resolver matches and `SSO_TENANCY_THROW_IF_MISSING=false`.

### Resolver order

1. Route parameter ULID  
2. Header (if enabled)  
3. Host/subdomain (if enabled)  
4. Default tenant ULID  

First non-null tenant wins.

## Multi-guard login

Each connection may set `guard` (e.g. `web`, `admin`). Resolution order:

1. `connection.guard`
2. `config('sso.guards.default')`
3. `config('auth.defaults.guard')`

Restrict allowed guards:

```env
SSO_ALLOWED_GUARDS=web,admin
```

Invalid or disallowed guards throw `GuardSelectionFailed`.

Ensure the target guard's provider model matches your user table and provisioning columns (`SSO_USER_EMAIL_COLUMN`, `SSO_USER_NAME_COLUMN`).

## Events

Subscribe in `EventServiceProvider` or use `Event::listen()`:

| Event | When |
|-------|------|
| `BeginLoginRequested` | Before auth attempt creation |
| `AuthAttemptCreated` | After attempt persisted |
| `BeginLoginRedirectGenerated` | After IdP redirect URL built |
| `CallbackSucceeded` | Protocol validation succeeded |
| `CallbackFailed` | Protocol validation failed |
| `UserProvisioned` | New local user created |
| `IdentityLinked` | External identity linked to existing user |
| `LoginCompleted` | User authenticated (login or returning external identity) |

Events carry `Tenant`, `Connection`, `IdentityProvider`, and sanitized callback summaries where applicable. They do **not** include raw tokens or SAML payloads.

Example:

```php
use CreativeCrafts\LaravelSso\Events\LoginCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(LoginCompleted::class, function (LoginCompleted $event): void {
    // Audit, analytics, session enrichment, etc.
});
```

## Custom protocol drivers

Implement `CreativeCrafts\LaravelSso\Contracts\Core\SsoDriver`:

```php
'drivers' => [
    'oidc' => \CreativeCrafts\LaravelSso\Drivers\OidcDriver::class,
    'saml' => \CreativeCrafts\LaravelSso\Drivers\SamlDriver::class,
    'custom' => \App\Sso\CustomDriver::class,
],
```

Drivers must implement `protocol()`, `start()`, and `handleCallback()`. Use existing OIDC/SAML drivers as references; do not bypass `HandleCallbackService` lifecycle unless you fully replicate auth-attempt binding.

## Repositories and models

Host applications may use Eloquent models directly (`Tenant`, `Connection`, `IdentityProvider`, etc.) or inject repository contracts for testability:

- `TenantRepository`
- `IdentityProviderRepository`
- `ConnectionRepository`
- `AuthAttemptRepository`
- `ExternalIdentityRepository`
- `AuditLogRepository`

All tenant-scoped repository methods enforce `tenant_id` boundaries.

## Extension contracts

Bind these contracts in a service provider to customize behavior without forking drivers:

| Contract | Default implementation | Purpose |
|----------|---------------------|---------|
| `UrlTrustPolicy` | `DefaultUrlTrustPolicy` | Validate redirect targets and outbound IdP URLs |
| `IdpOutboundUrlPolicy` | `DefaultIdpOutboundUrlPolicy` | SSRF-aware DNS checks for IdP HTTP calls |
| `AuditContextSanitizer` | `AuditContextSanitizer` | Redact audit log context |
| `ProvisioningPolicy` | `DefaultProvisioningPolicy` | Allow or deny JIT user creation |
| `IdentityLinkPolicy` | `DefaultIdentityLinkPolicy` | Allow or deny linking to existing users |
| `UserProvisioner` | `DefaultUserProvisioner` | Create local users during provisioning |
| `UserLocator` | `DefaultUserLocator` | Find users for identity linking |
| `TenantResolver` | Composite of route/header/host resolvers | Resolve tenant from the request |

Example:

```php
$this->app->bind(
    \CreativeCrafts\LaravelSso\Contracts\Core\ProvisioningPolicy::class,
    \App\Sso\GroupAwareProvisioningPolicy::class,
);
```

## Auth attempt consumption after protocol success

Protocol validation and account lifecycle are separate phases:

1. `HandleCallbackService` validates the OIDC/SAML callback and leaves the auth attempt **pending** (or releases a validation lock on failure).
2. `ProvisionAndLinkService` creates or links the local user and logs the user in.
3. `HandlesCallbackResponse` calls `markConsumed()` **after provisioning/linking succeeds**.

If provisioning or linking throws (for example `ProvisioningDenied`, `IdentityLinkDenied`, or `EmailVerificationRequired`), the auth attempt is still **consumed**. The user cannot retry the same IdP callback; they must start a new login from the redirect URL.

This prevents replay of a protocol-valid callback while denying account changes. Plan UX accordingly (redirect to an error page and offer a fresh login link).

See [Auth Attempt Lifecycle](auth-attempt-lifecycle.md).

## HTTP exception rendering

SSO route exceptions map to empty-body HTTP responses via `SsoExceptionRenderer`:

| Exception | Status |
|-----------|--------|
| `AuthAttemptAlreadyConsumed`, `AuthAttemptValidationInProgress`, `InvalidAuthAttemptBinding`, `SamlAssertionReplayDetected`, `UserEmailAlreadyExists` | 409 Conflict |
| `AuthAttemptExpired` | 410 Gone |
| `AuthAttemptNotFound`, `TenantNotFound`, `TenantScopedRecordNotFound`, `SsoResourceDisabled` | 404 Not Found |
| `IdentityLinkDenied`, `ProvisioningDenied`, `EmailVerificationRequired` | 403 Forbidden |
| `CallbackStateMissing`, `MissingExternalSubject`, `OidcCallbackCodeMissing`, `SamlAcsRequestInvalid`, `SamlResponseStatusInvalid`, `SamlSignatureMissing`, `UnsupportedSsoProtocol` | 400 Bad Request |
| `OidcIdTokenValidationFailed`, `OidcTokenExchangeFailed`, `SamlAssertionConditionsInvalid`, `SamlClaimsNormalizationFailed`, `SamlSignatureInvalid` | 401 Unauthorized |
| `OidcAuthorizationRequestFailed`, `OidcDiscoveryFailed`, `OidcEndpointResolutionFailed`, `OidcJwksFetchFailed`, `OidcUserinfoFailed`, `SamlAuthorizationRequestFailed`, `SamlMetadataParseFailed`, `TenantResolutionFailed`, `UnsafeIdpUrl` | 502 Bad Gateway |

Other exceptions fall through to Laravel's default handler.

See [Error Catalog](error-catalog.md) for operator guidance.

## User model requirements

Provisioning expects your user model to:

- Use the guard configured on the connection
- Allow mass assignment (or custom provisioner) for email and name columns
- Support unique email constraints if linking by email

Customize columns via `SSO_USER_EMAIL_COLUMN` and `SSO_USER_NAME_COLUMN`.

## Scheduled maintenance

Register pruning in `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sso:prune --attempts-days=7 --audit-days=30')->daily();
```

## Validation and diagnostics

```bash
php artisan sso:doctor          # Human-readable report (warnings + blocking issues)
php artisan sso:doctor --strict # Exit non-zero on blocking issues (CI-friendly)
php artisan sso:doctor --json   # Machine-readable output ({ status, ok, issues, warnings })
```

Blocking checks include: `APP_KEY`, migrations, drivers, OIDC timeout, registered routes, admin gate when UI enabled.

## Further reading

- [Configuration Reference](configuration-reference.md) — all config keys and IdP JSON shapes
- [Admin API](admin-api.md) — REST management surface
- [Security Guide](security.md) — hardening checklist
- [Auth Attempt Lifecycle](auth-attempt-lifecycle.md) — callback state machine
