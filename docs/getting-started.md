# Getting Started

This guide walks through a minimal OIDC setup from install to first successful login in about 15 minutes.

## 1. Install the package

```bash
composer require creativecrafts/laravel-sso
php artisan sso:install --run-migrations
```

`sso:install` publishes config (`sso-config`), migrations (`sso-migrations`), and optional UI assets (`sso-ui`), then optionally runs migrations.

Manual alternative:

```bash
php artisan vendor:publish --tag=sso-config
php artisan vendor:publish --tag=sso-migrations
php artisan migrate
```

## 2. Register the admin gate (optional but recommended)

If you will use the admin API or enable `SSO_UI_ENABLED`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('manageSso', fn ($user) => (bool) ($user->is_admin ?? false));
}
```

See [Admin API](admin-api.md) for full API workflow.

## 3. Create tenant, identity provider, and connection

```bash
php artisan sso:make-tenant "Acme Corp"
# Note the printed tenant ULID, e.g. 01JXXXXXXXXXXXXXXXXXXXXXXX

php artisan sso:make-idp 01JXXXXXXXXXXXXXXXXXXXXXXX "Acme OIDC" --protocol=oidc
# Note the printed identity provider id and ulid

php artisan sso:make-connection 01JXXXXXXXXXXXXXXXXXXXXXXX 1 "Acme OIDC Connection"
# Note the printed connection id and ulid
```

Replace ULIDs and IdP numeric `id` with values from your command output.

## 4. Configure the OIDC identity provider

Artisan commands create empty IdP `config` arrays. Set OIDC endpoints and credentials before testing login.

### Option A — Admin API (recommended)

Enable the admin API (`SSO_UI_ENABLED=true`), then update the IdP (replace ULIDs and use your real IdP values):

```bash
curl -X PUT "https://app.example.test/admin/sso/tenants/{tenant_ulid}/idps/{idp_id}" \
  -H "Content-Type: application/json" \
  -H "Cookie: ..." \
  -d '{
    "config": {
      "client_id": "your-client-id",
      "client_secret": "your-client-secret",
      "redirect_uri": "https://app.example.test/sso/{tenant_ulid}/{connection_ulid}/callback",
      "discovery_enabled": false,
      "userinfo_enabled": false,
      "endpoints": {
        "authorization": "https://idp.example.com/authorize",
        "token": "https://idp.example.com/token",
        "jwks": "https://idp.example.com/jwks"
      }
    }
  }'
```

Register the same `redirect_uri` with your OIDC provider.

### Option B — Tinker

```bash
php artisan tinker
```

```php
$idp = \CreativeCrafts\LaravelSso\Models\IdentityProvider::query()->find(1);
$idp->config = [
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://app.example.test/sso/{tenant_ulid}/{connection_ulid}/callback',
    'discovery_enabled' => false,
    'userinfo_enabled' => false,
    'endpoints' => [
        'authorization' => 'https://idp.example.com/authorize',
        'token' => 'https://idp.example.com/token',
        'jwks' => 'https://idp.example.com/jwks',
    ],
];
$idp->save();
```

See [Configuration Reference](configuration-reference.md) for full OIDC and SAML config shapes.

## 5. Add a login button

Use **connection ULID** in public URLs (not numeric id):

```blade
<x-sso-button
    tenant="01JXXXXXXXXXXXXXXXXXXXXXXX"
    connection="01JYYYYYYYYYYYYYYYYYYYYYYY"
    label="Sign in with SSO"
/>
```

Optional safe post-login redirect:

```blade
<x-sso-button
    tenant="01JXXXXXXXXXXXXXXXXXXXXXXX"
    connection="01JYYYYYYYYYYYYYYYYYYYYYYY"
    redirect-to="/dashboard"
/>
```

Or generate the URL in PHP:

```php
$url = sso_redirect_url($tenantUlid, $connectionUlid, redirectTo: '/dashboard');
```

## 6. Enable provisioning or linking (if needed)

By default, users are **not** auto-created or linked. To allow JIT provisioning for this connection:

```php
$connection = \CreativeCrafts\LaravelSso\Models\Connection::query()->first();
$connection->settings = ['allow_provisioning' => true];
$connection->save();
```

For claim-aware rules, bind custom policies — see [Integration Guide](integration-guide.md).

## 7. Verify and test

```bash
php artisan sso:doctor --strict
```

Visit the redirect URL or click the login button. Complete the IdP flow and confirm you return to the application.

## Next steps

| Topic | Document |
|-------|----------|
| All config keys and env vars | [Configuration Reference](configuration-reference.md) |
| Admin REST API | [Admin API](admin-api.md) |
| Policies, events, tenancy, guards | [Integration Guide](integration-guide.md) |
| Production rollout | [Deployment Guide](deployment-guide.md) |
| Security checklist | [Security Guide](security.md) |
| Callback state machine | [Auth Attempt Lifecycle](auth-attempt-lifecycle.md) |

Defaults in `config/sso.php` are safe for multi-tenant production; enable provisioning, linking, and SAML email trust only when you have reviewed the security implications.

## SAML quick start

Follow steps 1–3 above, then use SAML instead of OIDC:

```bash
php artisan sso:make-idp {tenant_ulid} "Acme SAML" --protocol=saml
php artisan sso:make-connection {tenant_ulid} {idp_id} "Acme SAML Connection"
```

Configure the IdP (admin API or tinker) with SAML endpoints, entity ID, and signing certificates. Register the ACS URL with your IdP:

```text
POST https://app.example.test/sso/{tenant_ulid}/{connection_ulid}/acs
```

Fetch SP metadata for IdP configuration:

```http
GET /sso/{tenant_ulid}/{connection_ulid}/metadata
```

Example IdP config shape (see [Configuration Reference](configuration-reference.md) for all keys):

```json
{
  "saml_sso_url": "https://idp.example.com/sso/saml",
  "saml_signing_certs_pem": [
    "-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----"
  ]
}
```

ACS binding is configured on the SP side (`sso.saml.sp.acs_binding` in `config/sso.php`), not in IdP JSON.

Begin login uses the same redirect route as OIDC (`/sso/{tenant}/{connection}/redirect`). The package issues a signed AuthnRequest when signing keys are configured; otherwise login proceeds unsigned (see `sso:doctor` warnings).

Login buttons and provisioning settings are identical to the OIDC flow above.
