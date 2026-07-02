# Admin API

The package ships a **JSON admin API** for managing tenants, identity providers, and connections. An optional **UI scaffold** can be published for host-app Inertia/Vite integration; full CRUD screens are not bundled — use this API or your own tooling.

Enable the admin API:

```env
SSO_UI_ENABLED=true
SSO_UI_PREFIX=admin/sso
SSO_UI_GATE=manageSso
SSO_UI_ALLOW_MISSING_GATE=false
```

## Authorization

All admin routes use middleware from `config('sso.ui.middleware')` (default `web`, `auth`) plus `can:{gate}`.

Register the gate in your host application (example):

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('manageSso', fn ($user) => $user->is_admin ?? false);
}
```

Form requests also call the gate directly as defense in depth.

Run `php artisan sso:doctor --strict` to verify the gate is registered when the admin API is enabled.

## Base URL

Default prefix: `/admin/sso`

Home/bootstrap endpoint:

```http
GET /admin/sso/
```

Returns JSON scaffold metadata (`SSO Admin UI scaffold`).

## Tenants

| Method | Path | Name |
|--------|------|------|
| GET | `/admin/sso/tenants` | `sso.ui.tenants.index` |
| POST | `/admin/sso/tenants` | `sso.ui.tenants.store` |
| GET | `/admin/sso/tenants/{tenant}` | `sso.ui.tenants.show` |
| PUT/PATCH | `/admin/sso/tenants/{tenant}` | `sso.ui.tenants.update` |
| DELETE | `/admin/sso/tenants/{tenant}` | `sso.ui.tenants.destroy` |

`{tenant}` is the tenant **ULID**.

### Create tenant

`ulid` is optional. When omitted or blank, the API auto-generates a ULID (same behavior as `sso:make-tenant`).

```http
POST /admin/sso/tenants
Content-Type: application/json

{
  "name": "Acme Corp",
  "metadata": {
    "domain": "acme.example.com",
    "domains": ["acme.example.com", "www.acme.example.com"],
    "subdomain": "acme"
  }
}
```

Response includes `ulid` — use this in public SSO URLs and nested routes.

Host/subdomain tenancy reads `metadata.domain`, `metadata.domains`, and `metadata.subdomain`. See [Integration Guide](integration-guide.md#host--subdomain-tenancy).

## Identity providers (tenant-scoped)

| Method | Path |
|--------|------|
| GET | `/admin/sso/tenants/{tenant}/idps` |
| POST | `/admin/sso/tenants/{tenant}/idps` |
| GET | `/admin/sso/tenants/{tenant}/idps/{idp}` |
| PUT/PATCH | `/admin/sso/tenants/{tenant}/idps/{idp}` |
| DELETE | `/admin/sso/tenants/{tenant}/idps/{idp}` |

`{idp}` accepts numeric ID or **ULID**.

### Create OIDC identity provider

```http
POST /admin/sso/tenants/{tenant_ulid}/idps
Content-Type: application/json

{
  "name": "Acme OIDC",
  "protocol": "oidc",
  "enabled": true,
  "config": {
    "client_id": "client-123",
    "client_secret": "secret-xyz",
    "redirect_uri": "https://app.example.com/sso/{tenant_ulid}/{connection_ulid}/callback",
    "discovery_enabled": false,
    "userinfo_enabled": false,
    "endpoints": {
      "authorization": "https://idp.example.com/authorize",
      "token": "https://idp.example.com/token",
      "jwks": "https://idp.example.com/jwks"
    }
  }
}
```

Replace `{tenant_ulid}` and `{connection_ulid}` with real ULIDs after creating the connection, or update `redirect_uri` when the connection exists.

### Create SAML identity provider

```http
POST /admin/sso/tenants/{tenant_ulid}/idps
Content-Type: application/json

{
  "name": "Acme SAML",
  "protocol": "saml",
  "enabled": true,
  "config": {
    "saml_sso_url": "https://idp.example.com/sso/saml",
    "saml_signing_certs_pem": ["-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----"]
  }
}
```

### Sensitive config in responses

Admin **show/index** responses **redact** sensitive keys (`client_secret`, tokens, private keys, etc.). Secrets are stored encrypted at rest; update secrets via PUT/PATCH with full config payloads.

## Connections (tenant-scoped)

| Method | Path |
|--------|------|
| GET | `/admin/sso/tenants/{tenant}/connections` |
| POST | `/admin/sso/tenants/{tenant}/connections` |
| GET | `/admin/sso/tenants/{tenant}/connections/{connection}` |
| PUT/PATCH | `/admin/sso/tenants/{tenant}/connections/{connection}` |
| DELETE | `/admin/sso/tenants/{tenant}/connections/{connection}` |

`{connection}` accepts numeric ID or **ULID**.

### Create connection

```http
POST /admin/sso/tenants/{tenant_ulid}/connections
Content-Type: application/json

{
  "identity_provider_id": 1,
  "name": "Default OIDC",
  "enabled": true,
  "guard": "web",
  "settings": {
    "allow_provisioning": false,
    "allow_identity_linking": false
  }
}
```

`identity_provider_id` may be a numeric ID or IdP **ULID**.

Response includes `id` and `ulid`. Use **`ulid`** in public SSO URLs and IdP redirect/ACS registration.

## End-to-end API workflow

1. `POST /admin/sso/tenants` → note `ulid`
2. `POST /admin/sso/tenants/{tenant}/idps` → note `id` / `ulid`
3. `POST /admin/sso/tenants/{tenant}/connections` → note `ulid`
4. `PUT /admin/sso/tenants/{tenant}/idps/{idp}` → set final OIDC `redirect_uri` with connection ULID
5. Register the same URLs with your IdP
6. Test: `GET /sso/{tenant_ulid}/{connection_ulid}/redirect`

## Optional UI scaffold

Publish Inertia/Vite scaffolding (host app wires the build):

```bash
php artisan vendor:publish --tag=sso-ui
```

Default destination: `resources/vendor/laravel-sso/ui`

See `resources/ui/README.md` in the package for integration notes.

## Blade login button

```blade
<x-sso-button
    tenant="{{ $tenantUlid }}"
    connection="{{ $connectionUlid }}"
    label="Sign in with SSO"
/>
```

Optional post-login redirect:

```blade
<x-sso-button
    tenant="{{ $tenantUlid }}"
    connection="{{ $connectionUlid }}"
    :redirect-to="url('/dashboard')"
/>
```

The component appends `redirect_to` to the generated redirect URL when provided.
