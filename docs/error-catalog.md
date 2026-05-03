# Error Catalog

This catalog maps common package failures to likely causes and operator actions.

## Tenant and connection errors

### Tenant cannot be resolved

Likely causes:

- missing route tenant parameter
- missing default tenant configuration
- disabled or misconfigured header/host tenancy resolver
- tenant ULID not present in `sso_tenants`

Operator actions:

1. Confirm the tenant exists.
2. Confirm route, header, host, or default tenancy configuration.
3. Run `php artisan sso:doctor`.

### Connection or identity provider not found

Likely causes:

- stale login URL
- disabled/deleted connection
- connection does not belong to the resolved tenant

Operator actions:

1. Verify the tenant and connection IDs in the route.
2. Confirm the connection belongs to the tenant.
3. Recreate the login URL from current connection data.

## Auth-attempt errors

### Auth attempt not found

Likely causes:

- expired or pruned state
- repeated callback after cleanup
- callback sent to the wrong tenant or connection

Operator action: restart the SSO login flow.

### Auth attempt expired

Likely causes:

- user waited beyond the configured attempt TTL
- IdP callback was delayed

Operator action: restart the login flow. Increase `sso.attempts.ttl_seconds` only when the IdP has consistently slow callbacks.

### Auth attempt already consumed

Likely causes:

- replayed callback
- browser retry after successful login
- duplicated IdP POST

Operator action: treat as replay protection working. Ask the user to start a new login if needed.

### Auth attempt validation in progress

Likely causes:

- duplicate callback while the first callback is still validating
- previous worker died and validation lock has not reached the configured TTL

Operator actions:

1. Retry after `sso.attempts.validation_lock_ttl_seconds`.
2. Check application worker logs for callback crashes.

## OIDC errors

### Discovery failed

Likely causes:

- invalid issuer or discovery URL
- IdP endpoint unavailable
- outbound URL policy rejected the host or DNS answer
- endpoint redirects but redirects are disabled

Operator actions:

1. Configure the final HTTPS discovery URL.
2. Confirm the hostname resolves to public routable addresses in production.
3. Check IdP availability from the application network.

### Token exchange failed

Likely causes:

- invalid client credentials
- redirect URI mismatch
- authorization code already used
- PKCE verifier mismatch
- IdP token endpoint rejected by outbound URL policy

Operator actions:

1. Compare the configured redirect URI with the IdP app registration.
2. Rotate client credentials if needed.
3. Restart the login flow after configuration changes.

### ID token validation failed

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

## SAML errors

### Missing or invalid signature

Likely causes:

- IdP did not sign the response or assertion
- configured signing certificate is stale
- SAML response was modified after signing
- unsupported document shape was received

Operator actions:

1. Confirm the IdP signs either the response or assertion.
2. Update configured signing certificates.
3. Keep old and new certificates configured during certificate rotation.

### Destination, audience, or recipient mismatch

Likely causes:

- ACS URL mismatch between app and IdP
- SP entity ID mismatch
- proxy/base URL misconfiguration
- wrong tenant or connection callback URL

Operator actions:

1. Confirm app URL and trusted proxy configuration.
2. Confirm the IdP ACS URL and entity ID.
3. Confirm the tenant and connection route values.

### Correlation mismatch

Likely causes:

- IdP response does not match the original AuthnRequest
- stale browser tab submitted an older response
- callback routed to the wrong connection

Operator action: restart the login flow and verify connection routing.

## Provisioning and linking errors

### Provisioning denied

Likely causes:

- package default denies provisioning
- connection does not opt in
- custom provisioning policy denied the user

Operator action: review the host application's onboarding policy before enabling provisioning.

### Identity linking denied

Likely causes:

- package default denies linking
- connection does not opt in
- custom linking policy denied the user

Operator action: review account-linking rules and collision handling before enabling linking.
