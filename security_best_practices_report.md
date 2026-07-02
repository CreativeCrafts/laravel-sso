# Security Best Practices Report — `creativecrafts/laravel-sso`

**Date:** 2026-07-01  
**Scope:** Entire package (PHP 8.3 / Laravel 12–13 SSO library: OIDC, SAML 2.0, multi-tenant provisioning, optional admin UI)  
**Reference guidance:** No PHP/Laravel-specific file exists in the security-best-practices skill references; this review applies general secure-coding guidance, Laravel package conventions, and OIDC/SAML threat models.

---

## Executive Summary

`creativecrafts/laravel-sso` is a **well-hardened SSO package** with secure-by-default provisioning/linking (deny by default), encrypted IdP configuration, PKCE + nonce validation, outbound IdP SSRF controls, auth-attempt lifecycle locking, rate limiting, and thorough SAML/OIDC validation. Recent remediation work addressed major findings including deferred auth-attempt consumption, email verification gates, SAML status/replay checks, and the provisioning-race identity-link bypass.

**No critical vulnerabilities were identified** in the current codebase. All **five medium** and **six low** findings from the original review have been **remediated** (see [Remediation Status](#remediation-status)). Dependency audit (`composer audit`) reports **no known advisories**.

---

## Remediation Status

**Updated:** 2026-07-01 — All findings below addressed; **176 tests passing**, PHPStan clean.

| ID | Status | Resolution |
|----|--------|------------|
| **M-1** | ✅ Fixed | `SamlDriver::maybeSignAuthnRequest()` throws `SamlAuthorizationRequestFailed::signingKeysMissing()` when signing is enabled but PEM keys are absent |
| **M-2** | ✅ Fixed | Public SSO routes accept connection **ULIDs** (numeric IDs retained for backward compatibility); `ConnectionRouteResolver` resolves route keys |
| **M-3** | ✅ Documented | SAML email trust flags documented in `docs/security.md`, deployment/upgrade guides |
| **M-4** | ✅ Fixed | Docblocks on default policies; example `GroupRequiredIdentityLinkPolicy` and `GroupRequiredProvisioningPolicy` |
| **M-5** | ✅ Fixed | `ConfigurableEncryptedArrayCast` + `sso.claims.encrypt_persisted` (default `true`) encrypt persisted external identity claims |
| **L-1** | ✅ Fixed | `SafeRedirectValidator` validates `redirect_to` at storage (`BeginLoginService`) and callback time |
| **L-2** | ✅ Documented | DNS TOCTOU limitation documented in `docs/security.md` |
| **L-3** | ✅ Fixed | `CachedSamlAssertionReplayGuard` uses atomic `cache->add()` |
| **L-4** | ✅ Fixed | `SsoResourceDisabled` maps to **404** in `SsoExceptionRenderer` |
| **L-5** | ✅ Fixed | Admin API accepts ULID or numeric route keys; responses include `ulid` |
| **L-6** | ✅ Documented | SAML ACS CSRF expectations documented in `docs/security.md` |

---

## Severity Legend

| Level | Meaning |
|-------|---------|
| **Critical** | Exploitable without special privileges; likely account takeover or auth bypass |
| **Medium** | Meaningful security weakness or misconfiguration risk in realistic deployments |
| **Low** | Defense-in-depth gap, informational leakage, or requires narrow conditions |
| **Informational** | Accepted trade-off, documentation gap, or integrator responsibility |

---

## Critical

*No findings.*

---

## Medium

### [M-1] SAML AuthnRequest signing silently skipped when misconfigured

**Location:** `src/Drivers/SamlDriver.php:230-241`

When `sso.saml.sp.sign_authn_requests` is `true` but PEM env vars are missing or empty, `maybeSignAuthnRequest()` returns the **unsigned** XML instead of failing closed.

**Impact:** Operators may believe AuthnRequests are signed while IdPs accept unsigned requests, weakening trust boundaries for strict IdPs.

**Recommendation:** Log a warning at minimum; prefer throwing `SamlAuthorizationRequestFailed` when signing is enabled but keys are absent.

---

### [M-2] Incrementing connection IDs exposed in public SSO URLs

**Location:** `routes/sso.php:16-30`, `src/helpers.php:7-18`

Public routes use `{tenant}` as ULID (good) but `{connection}` as a numeric database ID (e.g. `/sso/{tenant}/1/redirect`).

**Impact:** Attackers can enumerate connection IDs per tenant and probe disabled/misconfigured connections, aiding reconnaissance and targeted abuse of throttled endpoints.

**Recommendation:** Consider routing by connection ULID/slug in public URLs, or document that connection IDs are non-secret identifiers and rely on auth-attempt binding (already enforced).

---

### [M-3] SAML email attributes treated as verified when trust flags are enabled

**Location:** `config/sso.php:114,153`, `src/Core/ProvisionAndLinkService.php:356-371`

`SSO_PROVISIONING_TRUST_SAML_EMAIL` / `SSO_LINKING_TRUST_SAML_EMAIL` allow SAML `mail` attributes to satisfy email-verification requirements without an IdP-verified flag.

**Impact:** If enabled against an IdP that does not strongly authenticate email ownership, attackers could link or provision accounts for emails they do not control.

**Recommendation:** Keep defaults `false`; document prominently in README/install guide; consider per-connection overrides with explicit admin confirmation.

---

### [M-4] Default provisioning/linking policies ignore IdP claims

**Location:** `src/Policies/DefaultIdentityLinkPolicy.php:23-33`, `src/Policies/DefaultProvisioningPolicy.php:22-27`

Both policies accept a `$claims` array but only evaluate connection settings and package defaults—no inspection of groups, roles, or custom attributes.

**Impact:** Integrators enabling linking/provisioning may assume claim-based authorization is enforced by the package; unrestricted linking/provisioning could occur if connection flags are set too broadly.

**Recommendation:** Document that custom `IdentityLinkPolicy` / `ProvisioningPolicy` implementations **must** inspect `$claims` for attribute-based rules; optionally ship an example policy using groups.

---

### [M-5] External identity claims persisted without field-level encryption

**Location:** `src/Models/ExternalIdentity.php:37-38`, `src/Core/ProvisionAndLinkService.php:290-321`

`ExternalIdentity.claims` is stored as a plain JSON array. When `sso.claims.persist_raw` or group persistence is enabled, PII and authorization data land in the database unencrypted (unlike `IdentityProvider.config`, which uses `encrypted:array`).

**Impact:** Database compromise exposes stored emails, names, groups, and optionally raw protocol claims.

**Recommendation:** Document data classification; consider `encrypted:array` cast for claims, or minimize persisted fields by default (already mostly safe with defaults).

---

## Low

### [L-1] `redirect_to` stored without upfront validation

**Location:** `src/Core/BeginLoginService.php:60-61`, `src/Http/Controllers/Concerns/HandlesCallbackResponse.php:98-137`

The begin-login flow stores arbitrary `redirect_to` query values on the auth attempt. Sanitization occurs only at callback redirect time (same-origin / safe-path checks).

**Impact:** Low direct exploit risk because unsafe values fall back to `/`, but poisoned values may appear in logs/events and could become risky if redirect logic changes later.

**Recommendation:** Validate and normalize `redirect_to` at storage time using the same rules as `resolveRedirect()`.

---

### [L-2] DNS resolution TOCTOU for outbound IdP URLs

**Location:** `src/Core/DefaultIdpOutboundUrlPolicy.php:15-50`

Hostname resolution is checked once before HTTP requests; TTL/rebinding could theoretically change resolved addresses afterward. HTTP redirects are disabled on OIDC discovery/JWKS/token calls (mitigation noted in code).

**Impact:** Residual SSRF risk in adversarial DNS environments if redirect disabling were ever relaxed.

**Recommendation:** Keep redirects disabled; document TOCTOU limitation for security reviewers.

---

### [L-3] SAML assertion replay cache is non-atomic

**Location:** `src/Protocol/Saml/CachedSamlAssertionReplayGuard.php:17-35`

Replay detection uses separate `has()` and `put()` cache operations without atomic compare-and-set.

**Impact:** Concurrent identical assertion replays within a narrow window may both pass before `markConsumed()`; primary mitigation is auth-attempt row locking.

**Recommendation:** Use atomic cache add (`add()` / `lock`) if cache driver supports it; acceptable as defense-in-depth given attempt locking.

---

### [L-4] Disabled vs missing resource status codes differ

**Location:** `src/Core/BeginLoginService.php:44-57`, `src/Http/SsoExceptionRenderer.php:28-38`

Disabled connections/IdPs raise `SsoResourceDisabled` (403); missing records raise `TenantScopedRecordNotFound` (404).

**Impact:** Minor enumeration of disabled vs non-existent resources on begin-login URLs.

**Recommendation:** Acceptable trade-off; unify to 404 if enumeration is a concern.

---

### [L-5] Admin API uses integer resource IDs

**Location:** `routes/admin.php:36-47`, `src/Http/Controllers/Admin/IdentityProvidersController.php:59-67`

Admin routes scope by tenant ULID but use integer `{idp}` and `{connection}` path segments (protected by `auth` + `can:manageSso`).

**Impact:** Low for authenticated admins; predictable IDs in admin-only APIs.

**Recommendation:** Optional ULID/slug routing for consistency with public tenant identifiers.

---

### [L-6] SAML ACS POST intentionally lacks CSRF tokens

**Location:** `routes/sso.php:24-26`

SAML HTTP-POST binding cannot include Laravel CSRF tokens; security relies on signed assertions, `InResponseTo`, audience/destination checks, and auth-attempt state.

**Impact:** None under standard SAML threat model; document as expected for integrators unfamiliar with SAML.

---

## Informational / Strengths

The following controls are implemented well and align with security best practices:

| Area | Implementation |
|------|----------------|
| **Secure defaults** | Provisioning and linking denied by default (`config/sso.php:108,147`) |
| **OIDC** | PKCE S256, nonce binding, RS256-only verification, JWKS signature check, issuer/aud/azp validation (`DefaultOidcIdTokenValidator.php`) |
| **SAML** | LIBXML_NONET, signature + Status validation, assertion conditions, optional replay guard, XML escaping in AuthnRequest (`DefaultSamlSignatureValidator.php`, `SamlDriver.php`) |
| **SSRF** | HTTPS enforcement, blocked credentials in URLs, private/reserved host blocking, DNS resolution checks (`DefaultUrlTrustPolicy.php`, `DefaultIdpOutboundUrlPolicy.php`) |
| **Auth attempts** | Cryptographic state/nonce lengths, row-lock validation lifecycle, deferred consumption, terminal consume on failure (`DbAuthAttemptService.php`, `HandlesCallbackResponse.php`) |
| **Account integrity** | Email verification required for link/provision; provisioning race routes through linking policy (`ProvisionAndLinkService.php`, `DefaultUserProvisioner.php`) |
| **Secrets** | IdP `config` encrypted at rest; admin API redacts sensitive keys (`IdentityProvider.php:33`, `IdentityProvidersController.php:163-179`) |
| **Rate limiting** | Separate limiters for redirect, callback, ACS, metadata (`LaravelSsoServiceProvider.php:270-314`) |
| **Open redirect** | Callback redirect sanitization with same-origin and path checks (`HandlesCallbackResponse.php:98-160`) |
| **Audit** | Redacted audit context by default; extended context opt-in (`AuditContextSanitizer.php`) |
| **Mass assignment** | Models use explicit `$fillable` arrays |
| **Dependencies** | `composer audit` — no advisories at review time |
| **Architecture** | Arch tests restrict controllers from HTTP clients and drivers from repositories (`tests/ArchTest.php`) |

---

## Operational Checklist for Deployments

1. Keep `SSO_PROVISIONING_ENABLED=false` and `SSO_LINKING_ENABLED=false` unless explicitly required.
2. Never enable `SSO_*_TRUST_SAML_EMAIL` unless the IdP strongly attests email ownership.
3. Never set `SSO_ALLOW_INSECURE_IDP_URLS` or `SSO_ALLOW_PRIVATE_IDP_URLS` in production.
4. Register and enforce the `manageSso` gate; keep `SSO_UI_ALLOW_MISSING_GATE=false`.
5. If enabling SAML AuthnRequest signing, ensure PEM keys are present or expect unsigned requests.
6. Configure `SSO_ALLOWED_GUARDS` when using multiple auth guards.
7. Run `composer audit` in CI (already in package `ci` script).

---

## Fix Priority

| Priority | IDs | Effort |
|----------|-----|--------|
| 1 | M-1 (fail closed on missing signing keys) | Small |
| 2 | L-1 (validate redirect_to at storage) | Small |
| 3 | M-4 (documentation + example claim-aware policies) | Medium |
| 4 | M-2, M-5, L-3 | Medium–Large |

---

## Verdict

**Security review found 0 critical, 5 medium, and 6 low findings — all remediated.** The package is suitable for production use with secure defaults; integrators should follow `docs/security.md` and the operational checklist below.
