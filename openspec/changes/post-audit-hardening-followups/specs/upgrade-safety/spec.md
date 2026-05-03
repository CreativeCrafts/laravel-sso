# Upgrade Safety Spec Delta

## ADDED Requirements

### Requirement: Existing installations receive schema upgrades

The package SHALL provide upgrade migrations for schema fields required by runtime code when those fields were added after the original published migration stub.

#### Scenario: Auth-attempt lifecycle fields are added to an old schema

- **GIVEN** an existing installation with `sso_auth_attempts` but without `status`, `validating_at`, or `failed_at`
- **WHEN** package upgrade migrations run
- **THEN** the missing lifecycle columns are added
- **AND** runtime auth-attempt creation and callback validation do not fail due to unknown columns.

#### Scenario: Existing consumed attempts are backfilled

- **GIVEN** an existing `sso_auth_attempts` row with `consumed_at` set
- **WHEN** package upgrade migrations run
- **THEN** the row status is backfilled to `consumed`.

#### Scenario: Existing non-consumed attempts are backfilled

- **GIVEN** an existing `sso_auth_attempts` row without `consumed_at`
- **WHEN** package upgrade migrations run
- **THEN** the row status is backfilled to `pending`.

### Requirement: Encrypted PKCE verifier rollout is documented

The package SHALL document upgrade behavior for existing plaintext `code_verifier` values before encrypted casts are used in production.

#### Scenario: Operator prepares for encrypted verifier rollout

- **GIVEN** an operator is upgrading from a version that stored plaintext PKCE verifiers
- **WHEN** they read the upgrade guide
- **THEN** they are instructed to prune or let expire existing auth attempts before deployment
- **AND** they are told active login attempts may need to be retried.