# Auth Attempt Lifecycle

Auth attempts are short-lived protocol state for OIDC and SAML login callbacks.

See also: [Error Catalog](error-catalog.md#auth-attempt-errors), [Integration Guide](integration-guide.md#http-exception-rendering), [Configuration Reference](configuration-reference.md#auth-attempts).

## States

The public model exposes these status constants:

- `AuthAttempt::STATUS_PENDING`
- `AuthAttempt::STATUS_VALIDATING`
- `AuthAttempt::STATUS_CONSUMED`
- `AuthAttempt::STATUS_FAILED`

Runtime callback handling uses the first three states as the active lifecycle.

`STATUS_FAILED` is reserved for legacy/manual diagnostic states. Runtime retryable validation failures do not transition to `failed`.

## Canonical callback lifecycle

Use this sequence for protocol callbacks:

1. `AuthAttemptService::reserveForValidation()`
2. perform provider-specific validation
3. `AuthAttemptService::markConsumed()` on success
4. `AuthAttemptService::markValidationFailed()` on retryable validation failure

This preserves replay protection without consuming an attempt before protocol validation succeeds.

## Retryable failures

A retryable validation failure is represented as:

```text
status = pending
failed_at = timestamp
validating_at = null
consumed_at = null
```

This lets a callback retry while the attempt remains unexpired and unconsumed. The `failed_at` column records the most recent failed validation attempt for debugging and audit context.

## Consumed attempts

A consumed attempt is terminal:

```text
status = consumed
consumed_at = timestamp
```

Consumed attempts must reject replayed callbacks even when `failed_at` was previously set by a retryable failure.

## Protocol success vs account lifecycle

After OIDC/SAML validation succeeds, `HandlesCallbackResponse` runs provisioning/linking. The auth attempt is marked **consumed** whether provisioning succeeds or throws (for example `ProvisioningDenied`). Users must start a **new** login to retry; the IdP callback URL cannot be reused.

See [Integration Guide](integration-guide.md#auth-attempt-consumption-after-protocol-success).

## `consumeByState()`

`consumeByState()` remains available as a replay-safe atomic helper. It reserves and consumes in one operation.

Do not use it for OIDC or SAML callbacks that must validate provider data before consumption. Use the reserve/validate/consume sequence instead.
