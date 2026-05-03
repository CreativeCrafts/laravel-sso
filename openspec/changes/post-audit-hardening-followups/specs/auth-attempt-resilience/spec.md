# Auth Attempt Resilience Spec Delta

## ADDED Requirements

### Requirement: Stale validation locks recover safely

The package SHALL recover auth attempts abandoned in `validating` state after a configurable lock timeout while preserving replay protection.

#### Scenario: Fresh validating attempt is rejected

- **GIVEN** an auth attempt is in `validating` state
- **AND** `validating_at` is newer than `sso.attempts.validation_lock_ttl_seconds`
- **WHEN** a callback tries to reserve the attempt
- **THEN** reservation is rejected as validation already in progress.

#### Scenario: Stale validating attempt is recoverable

- **GIVEN** an auth attempt is in `validating` state
- **AND** `validating_at` is older than `sso.attempts.validation_lock_ttl_seconds`
- **AND** the attempt is not consumed or expired
- **WHEN** a callback tries to reserve the attempt
- **THEN** the attempt is reserved again under row lock.

#### Scenario: Consumed attempt remains rejected

- **GIVEN** an auth attempt is consumed
- **WHEN** a callback tries to reserve the attempt
- **THEN** reservation is rejected even if `validating_at` is stale.

#### Scenario: Expired attempt remains rejected

- **GIVEN** an auth attempt is expired
- **WHEN** a callback tries to reserve the attempt
- **THEN** reservation is rejected even if `validating_at` is stale.

### Requirement: Retryable failure state is explicit

The package SHALL document whether failed protocol validation returns the attempt to `pending` for retry or moves it to a terminal failure state.

#### Scenario: Failed validation is retryable

- **GIVEN** protocol validation fails after reservation
- **WHEN** the package releases the attempt for retry
- **THEN** `failed_at` records the failure time
- **AND** the status semantics are documented.