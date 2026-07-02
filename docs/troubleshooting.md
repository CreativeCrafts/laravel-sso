# Troubleshooting Guide

Use this guide when SSO login, callback validation, provisioning, or audit behavior does not match expectations.

## Start with the doctor

```bash
php artisan sso:doctor
php artisan sso:doctor --strict   # CI / pre-deploy: fail on blocking issues
php artisan sso:doctor --json     # automation-friendly output
```

JSON output shape:

```json
{
  "status": "ok",
  "ok": true,
  "issues": [],
  "warnings": []
}
```

`status` is `ok`, `warnings`, or `issues`. Use `--strict` so the exit code is non-zero when `issues` is non-empty.

**Blocking issues** (errors) include missing `APP_KEY`, missing migrations, unregistered routes, missing admin gate, invalid drivers/timeouts.

**Warnings** include unset SAML SP entity ID (when SAML may be unused), package-wide provisioning/linking enabled, or insecure IdP URL overrides.

Resolve blocking issues before debugging protocol-specific behavior.

## Login does not redirect to the IdP

Check:

- the tenant exists and can be resolved ([Integration Guide](integration-guide.md#tenancy))
- the connection exists and belongs to the tenant (use **ULID** in URL)
- the connection's identity provider exists and has valid `config`
- `SSO_ENABLED` and `SSO_ROUTES_ENABLED` are true
- route middleware allows the current session to start SSO
- the protocol driver is registered in `config/sso.php`

For OIDC, verify the authorization endpoint can be resolved from configured discovery or manual endpoints.

For SAML, verify `config.saml_sso_url` is present and trusted by the URL policy.

Disabled connections return **404** on begin-login — check `enabled` flags.

## Wrong connection or tenant in URL

Public URLs must use:

```text
/sso/{tenant_ulid}/{connection_ulid}/redirect
```

Numeric connection IDs still work but are not recommended. ULIDs are printed by `sso:make-connection` and returned by the admin API.

## OIDC discovery or JWKS fetch fails

Check:

- issuer or discovery URL is correct
- endpoint uses HTTPS
- endpoint does not require following redirects
- hostname resolves from the application network
- hostname resolves to public routable addresses in production
- JWKS payload has a `keys` array

For local development, private IdP URLs require explicit opt-in:

```env
SSO_ALLOW_PRIVATE_IDP_URLS=true
```

Do not enable that override in production without compensating network controls.

## OIDC callback fails

Check:

- IdP redirect URI **exactly** matches the application callback URL (with connection ULID)
- authorization code has not already been used
- client ID and client secret are correct
- auth attempt has not expired
- token endpoint accepts PKCE verifier
- ID token issuer, audience, expiry, nonce, key ID, and signature are valid
- `auth_time` is present when `max_age_seconds` is configured

Users with stale login tabs should restart the login flow.

## SAML ACS fails

Check:

- ACS URL in IdP matches the deployed application URL (connection ULID)
- IdP signs the response or assertion
- configured signing certificate is current
- response `Destination` matches the ACS URL
- assertion `Audience` matches SP entity ID
- `SubjectConfirmationData Recipient` matches ACS URL
- `InResponseTo` matches the original AuthnRequest ID

The package rejects ambiguous SAML document shapes before accepting signatures, including multiple assertions, nested assertions, duplicate IDs, encrypted assertions, and missing signatures.

SAML ACS returns **409** on assertion replay — start a fresh login.

## Email verification or linking/provisioning denied

Returns **403** on public routes.

Check:

- OIDC `email_verified` claim is true when required
- SAML email trust flags are not enabled unless appropriate
- connection `allow_provisioning` / `allow_identity_linking` settings
- custom policy group requirements (`required_link_groups`, `required_provision_groups`)

See [Error Catalog](error-catalog.md#provisioning-and-linking-errors).

## Provisioning does not create a user

Provisioning is denied by default.

Check:

- package-wide provisioning default (`SSO_PROVISIONING_ENABLED`)
- connection-level `allow_provisioning` setting
- custom `ProvisioningPolicy` binding
- user model fillable/guarded settings
- configured email and name columns (`SSO_USER_*`)

## Identity linking does not attach an external identity

Identity linking is denied by default.

Check:

- package-wide linking default (`SSO_LINKING_ENABLED`)
- connection-level `allow_identity_linking` setting
- custom `IdentityLinkPolicy` binding
- whether a user with the incoming email already exists
- external subject and provider identifiers

## Multi-guard login issues

Check:

- `connection.guard` value exists in `config/auth.php`
- guard is listed in `SSO_ALLOWED_GUARDS` when allowlist is configured
- user provider model matches provisioning column config

## Audit context contains less data than expected

Audit logging is intentionally redacted by default.

If more debugging context is required temporarily:

```env
SSO_AUDIT_EXTENDED_CONTEXT=true
```

Even in extended mode, raw tokens, raw SAML responses, private keys, and raw claim bags are redacted.

## Redirect after login goes to `/`

Unsafe post-login redirects fall back to `/`.

Rejected targets include:

- external origins
- protocol-relative URLs
- malformed URLs
- backslash-containing values
- control characters

Use local paths such as `/dashboard` or same-origin absolute URLs. Pass via `redirect_to` query param or `<x-sso-button redirect-to="...">`.

## Admin API returns 403

Check:

- user is authenticated
- `manageSso` gate is defined and returns true
- `SSO_UI_ALLOW_MISSING_GATE=false` (recommended)

## Stale auth attempts accumulate

Schedule pruning:

```php
Schedule::command('sso:prune --attempts-days=7 --audit-days=30')->daily();
```

During upgrades involving encrypted transient data, prune or let existing auth attempts expire before deployment ([Upgrade Guide](upgrade-guide.md)).

## Related documentation

- [Error Catalog](error-catalog.md)
- [Configuration Reference](configuration-reference.md)
- [Admin API](admin-api.md)
- [Integration Guide](integration-guide.md)
