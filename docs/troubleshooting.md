# Troubleshooting Guide

Use this guide when SSO login, callback validation, provisioning, or audit behavior does not match expectations.

## Start with the doctor

Run:

```bash
php artisan sso:doctor
```

Resolve configuration, route, migration, tenancy, and URL warnings before debugging protocol-specific behavior.

## Login does not redirect to the IdP

Check:

- the tenant exists and can be resolved
- the connection exists and belongs to the tenant
- the connection's identity provider exists
- package routes are enabled
- route middleware allows the current user/session to start SSO
- the protocol driver is registered in `config/sso.php`

For OIDC, verify the authorization endpoint can be resolved from configured discovery or manual endpoints.

For SAML, verify `config.saml_sso_url` is present and trusted by the URL policy.

## OIDC discovery or JWKS fetch fails

Check:

- issuer or discovery URL is correct
- endpoint uses HTTPS
- endpoint does not require following redirects
- hostname resolves from the application network
- hostname resolves to public routable addresses in production
- JWKS payload has a `keys` array

For local development, private IdP URLs require explicit opt-in:

```php
'security' => [
    'allow_private_idp_urls' => true,
]
```

Do not enable that override in production without compensating network controls.

## OIDC callback fails

Check:

- IdP redirect URI exactly matches the application callback URL
- authorization code has not already been used
- client ID and client secret are correct
- auth attempt has not expired
- token endpoint accepts PKCE verifier
- ID token issuer, audience, expiry, nonce, key ID, and signature are valid
- `auth_time` is present when `max_age_seconds` is configured

Users with stale login tabs should restart the login flow.

## SAML ACS fails

Check:

- ACS URL in IdP matches the deployed application URL
- IdP signs the response or assertion
- configured signing certificate is current
- response `Destination` matches the ACS URL
- assertion `Audience` matches SP entity ID
- `SubjectConfirmationData Recipient` matches ACS URL
- `InResponseTo` matches the original AuthnRequest ID

The package rejects ambiguous SAML document shapes before accepting signatures, including multiple assertions, nested assertions, duplicate IDs, encrypted assertions, and missing signatures.

## Provisioning does not create a user

Provisioning is denied by default.

Check:

- package-wide provisioning default
- connection-level `allow_provisioning` setting
- custom `ProvisioningPolicy` binding
- user model fillable/guarded settings
- configured email and name columns

## Identity linking does not attach an external identity

Identity linking is denied by default.

Check:

- package-wide linking default
- connection-level `allow_identity_linking` setting
- custom `IdentityLinkPolicy` binding
- whether a user with the incoming email already exists
- external subject and provider identifiers

## Audit context contains less data than expected

Audit logging is intentionally redacted by default.

If more debugging context is required temporarily:

```php
'audit' => [
    'extended_context' => true,
]
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

Use local paths such as `/dashboard` or same-origin absolute URLs.

## Stale auth attempts accumulate

Schedule pruning:

```bash
php artisan sso:prune --attempts-days=7 --audit-days=30
```

During upgrades involving encrypted transient data, prune or let existing auth attempts expire before deployment.
