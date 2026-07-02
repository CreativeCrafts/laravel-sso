# Error Catalog

This catalog maps package failures to likely causes, HTTP responses (on public SSO routes where applicable), and operator actions.

## HTTP status quick reference (public SSO routes)

Public SSO routes return **empty bodies** with explicit status codes via `SsoExceptionRenderer`. Admin API routes use Laravel's default exception handling unless noted.

| HTTP | Meaning | Common exceptions |
|------|---------|-------------------|
| 400 | Malformed callback request | `CallbackStateMissing`, `MissingExternalSubject`, `OidcCallbackCodeMissing`, `SamlAcsRequestInvalid`, `SamlResponseStatusInvalid`, `SamlSignatureMissing`, `UnsupportedSsoProtocol` |
| 401 | Protocol validation failed | `OidcCallbackErrorResponse`, `OidcIdTokenValidationFailed`, `OidcTokenExchangeFailed`, `SamlAssertionConditionsInvalid`, `SamlClaimsNormalizationFailed`, `SamlSignatureInvalid` |
| 403 | Policy or verification denial | `ProvisioningDenied`, `IdentityLinkDenied`, `EmailVerificationRequired` |
| 404 | Missing or disabled resource | `AuthAttemptNotFound`, `TenantNotFound`, `TenantScopedRecordNotFound`, `SsoResourceDisabled` |
| 409 | Replay, binding conflict, or concurrent validation | `AuthAttemptAlreadyConsumed`, `AuthAttemptValidationInProgress`, `InvalidAuthAttemptBinding`, `SamlAssertionReplayDetected`, `UserEmailAlreadyExists` |
| 410 | Expired auth attempt | `AuthAttemptExpired` |
| 500 | Guard misconfiguration | `GuardSelectionFailed` |
| 502 | IdP or outbound resolution failure | `OidcAuthorizationRequestFailed`, `OidcDiscoveryFailed`, `OidcEndpointResolutionFailed`, `OidcJwksFetchFailed`, `OidcUserinfoFailed`, `SamlAuthorizationRequestFailed`, `SamlMetadataParseFailed`, `TenantResolutionFailed`, `UnsafeIdpUrl` |

Other unlisted exceptions fall through to Laravel's default handler and may return **500** in production or a debug page when `APP_DEBUG=true`.

## Audit log events

Callback handling writes redacted audit records to `sso_audit_logs`:

| Event | Level | When |
|-------|-------|------|
| `sso.callback.succeeded` | `info` | Protocol validation succeeded (before provisioning/linking) |
| `sso.callback.failed` | `warning` | Protocol validation failed |

Extended context is opt-in via `SSO_AUDIT_EXTENDED_CONTEXT=true`. See [Configuration Reference](configuration-reference.md#audit-logging).

---

## Tenant and connection errors

### Tenant cannot be resolved

**Exceptions:** `TenantResolutionFailed` → **502** on public SSO routes; missing route tenant parameter → **404** (`abort(404)` in controllers)

Likely causes:

- missing route tenant parameter
- missing default tenant configuration
- disabled or misconfigured header/host tenancy resolver
- tenant ULID not present in `sso_tenants`

Operator actions:

1. Confirm the tenant exists.
2. Confirm route, header, host, or default tenancy configuration ([Integration Guide](integration-guide.md#tenancy)).
3. Run `php artisan sso:doctor --strict`.

### Connection or identity provider not found

**Exception:** `TenantScopedRecordNotFound` → **404** on public routes

Likely causes:

- stale login URL (wrong ULID)
- disabled/deleted connection
- connection does not belong to the resolved tenant

Operator actions:

1. Verify tenant and connection ULIDs in the route.
2. Confirm the connection belongs to the tenant.
3. Recreate the login URL from current connection data ([Admin API](admin-api.md)).

### SSO resource disabled

**Exception:** `SsoResourceDisabled` → **404** on public begin-login routes

Likely causes:

- connection or identity provider `enabled=false`

Operator action: re-enable the resource or use an active connection.

---

## Auth-attempt errors

See [Auth Attempt Lifecycle](auth-attempt-lifecycle.md).

### Auth attempt not found

**Exception:** `AuthAttemptNotFound`

Likely causes:

- expired or pruned state
- repeated callback after cleanup
- callback sent to the wrong tenant or connection
- missing or invalid `state` / `RelayState`

Operator action: restart the SSO login flow.

### Auth attempt expired

**Exception:** `AuthAttemptExpired` → **410**

Likely causes:

- user waited beyond `sso.attempts.ttl_seconds`
- IdP callback was delayed

Operator action: restart the login flow. Increase TTL only when the IdP has consistently slow callbacks.

### Auth attempt already consumed

**Exception:** `AuthAttemptAlreadyConsumed` → **409**

Likely causes:

- replayed callback
- browser retry after successful login
- duplicated IdP POST

Operator action: replay protection is working. Ask the user to start a new login if needed.

### Auth attempt validation in progress

**Exception:** `AuthAttemptValidationInProgress` → **409**

Likely causes:

- duplicate callback while the first callback is still validating
- previous worker died before releasing the validation lock

Operator actions:

1. Retry after `sso.attempts.validation_lock_ttl_seconds`.
2. Check application worker logs for callback crashes.

### Invalid auth attempt binding

**Exception:** `InvalidAuthAttemptBinding`

Likely causes:

- callback routed to a different connection than the attempt was created for
- tampered or mismatched state

Operator action: restart login; verify URL uses the correct connection ULID.

### Callback state missing

**Exception:** `CallbackStateMissing`

Likely causes:

- OIDC callback without `state` query parameter
- SAML ACS without `RelayState`

Operator action: restart login; verify IdP preserves state/RelayState.

---

## OIDC errors

### Discovery failed

**Exception:** `OidcDiscoveryFailed`, `UnsafeIdpUrl`

Likely causes:

- invalid issuer or discovery URL
- IdP endpoint unavailable
- outbound URL policy rejected the host or DNS answer
- endpoint redirects but redirects are disabled

Operator actions:

1. Configure the final HTTPS discovery URL or manual endpoints.
2. Confirm the hostname resolves to public routable addresses in production.
3. Check IdP availability from the application network.

### Endpoint resolution failed

**Exception:** `OidcEndpointResolutionFailed`

Likely causes:

- incomplete IdP config (missing issuer, discovery_url, or endpoints)
- discovery disabled without manual endpoints

Operator action: review OIDC config shape in [Configuration Reference](configuration-reference.md).

### Token exchange failed

**Exception:** `OidcTokenExchangeFailed`

Likely causes:

- invalid client credentials
- redirect URI mismatch
- authorization code already used
- PKCE verifier mismatch
- IdP token endpoint rejected by outbound URL policy

Operator actions:

1. Compare the configured redirect URI with the IdP app registration (use connection ULID).
2. Rotate client credentials if needed.
3. Restart the login flow after configuration changes.

### OIDC callback code missing

**Exception:** `OidcCallbackCodeMissing`

Likely causes:

- user denied consent at IdP
- malformed callback query string

Operator action: check for IdP error parameters; restart login.

### OIDC callback error response

**Exception:** `OidcCallbackErrorResponse`

Likely causes:

- IdP returned OAuth error (`error`, `error_description`)

Operator action: inspect IdP logs and callback query parameters.

### ID token validation failed

**Exception:** `OidcIdTokenValidationFailed`

Likely causes:

- unknown key ID
- issuer or audience mismatch
- expired token
- nonce mismatch
- invalid `azp` for multi-audience token
- malformed temporal claims
- configured `max_age_seconds` requires `auth_time`

Operator actions:

1. Confirm issuer/client ID configuration.
2. Confirm JWKS endpoint availability.
3. Restart the login flow.
4. Check IdP token settings.

### JWKS fetch failed

**Exception:** `OidcJwksFetchFailed`

Likely causes:

- JWKS URL unreachable or blocked by URL policy
- invalid JWKS JSON

Operator action: verify JWKS endpoint and outbound connectivity.

### Userinfo failed

**Exception:** `OidcUserinfoFailed`

Likely causes:

- userinfo enabled but endpoint misconfigured or unavailable

Operator action: disable userinfo if not required, or fix endpoint config.

---

## SAML errors

### Missing or invalid signature

**Exceptions:** `SamlSignatureMissing`, `SamlSignatureInvalid`

Likely causes:

- IdP did not sign the response or assertion
- configured signing certificate is stale
- SAML response was modified after signing
- unsupported document shape was received

Operator actions:

1. Confirm the IdP signs either the response or assertion.
2. Update configured signing certificates.
3. Keep old and new certificates configured during certificate rotation.

### SAML response status invalid

**Exception:** `SamlResponseStatusInvalid`

Likely causes:

- IdP returned error status (not Success)
- misconfigured IdP SSO flow

Operator action: inspect IdP SAML response status and logs.

### Destination, audience, or recipient mismatch

**Exception:** `SamlAssertionConditionsInvalid`

Likely causes:

- ACS URL mismatch between app and IdP
- SP entity ID mismatch
- proxy/base URL misconfiguration
- wrong tenant or connection callback URL

Operator actions:

1. Confirm app URL and trusted proxy configuration.
2. Confirm the IdP ACS URL and entity ID use connection ULIDs.
3. Confirm tenant and connection route values.

### SAML assertion replay

**Exception:** `SamlAssertionReplayDetected` → **409**

Likely causes:

- duplicated SAMLResponse with the same assertion ID within cache TTL

Operator action: replay protection is working; user should start a new login if needed.

### SAML ACS request invalid

**Exception:** `SamlAcsRequestInvalid`

Likely causes:

- missing or empty `SAMLResponse` POST body
- malformed base64 payload

Operator action: verify IdP POST binding configuration.

### Correlation mismatch

Likely causes:

- IdP response does not match the original AuthnRequest
- stale browser tab submitted an older response
- callback routed to the wrong connection

Operator action: restart the login flow and verify connection routing.

---

## Provisioning and linking errors

### Provisioning denied

**Exception:** `ProvisioningDenied` → **403**

Likely causes:

- package default denies provisioning
- connection does not opt in
- custom provisioning policy denied the user (e.g. missing required group)

Operator action: review onboarding policy before enabling provisioning ([Integration Guide](integration-guide.md)).

### Identity linking denied

**Exception:** `IdentityLinkDenied` → **403**

Likely causes:

- package default denies linking
- connection does not opt in
- custom linking policy denied the user

Operator action: review account-linking rules before enabling linking.

### Email verification required

**Exception:** `EmailVerificationRequired` → **403**

Likely causes:

- OIDC token lacks `email_verified=true`
- SAML email trust flags disabled and no verified email claim

Operator actions:

1. Configure IdP to emit verified email claims.
2. Do not enable SAML email trust unless the IdP attests ownership ([Security Guide](security.md)).

### Missing external subject

**Exception:** `MissingExternalSubject`

Likely causes:

- IdP token/assertion lacks a subject identifier

Operator action: configure IdP to emit `sub` / NameID.

### Guard selection failed

**Exception:** `GuardSelectionFailed` → **500** on public SSO routes

Likely causes:

- connection guard not configured in `config/auth.php`
- guard not in `SSO_ALLOWED_GUARDS` allowlist

Operator action: fix guard configuration ([Integration Guide](integration-guide.md#multi-guard-login)).

---

## Related documentation

- [Troubleshooting Guide](troubleshooting.md)
- [Security Guide](security.md)
- [Configuration Reference](configuration-reference.md)
