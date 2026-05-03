# Documentation and Developer Experience Spec Delta

## ADDED Requirements

### Requirement: README local documentation links are valid

The package SHALL keep README links to local documentation files valid.

#### Scenario: README links a local docs file

- **GIVEN** README contains a relative link to `docs/*.md`
- **WHEN** documentation integrity checks run
- **THEN** the linked file exists in the repository.

### Requirement: Upgrade guide documents release-sensitive behavior

The package SHALL include an upgrade guide for schema, encryption, and security-default changes.

#### Scenario: Operator upgrades across auth-attempt lifecycle changes

- **GIVEN** an operator reads `docs/upgrade-guide.md`
- **WHEN** they upgrade to a version requiring auth-attempt lifecycle fields
- **THEN** the guide explains required migrations, backfills, and safe rollout order.

#### Scenario: Operator upgrades across PKCE verifier encryption

- **GIVEN** an operator reads `docs/upgrade-guide.md`
- **WHEN** they upgrade to encrypted transient verifier storage
- **THEN** the guide explains pruning or allowing existing attempts to expire before deploy.

### Requirement: Local matrix testing is documented

The package SHALL document how contributors can test supported Laravel dependency sets locally.

#### Scenario: Contributor wants Laravel 12 local validation

- **GIVEN** a contributor wants to validate against Laravel 12
- **WHEN** they read contributor documentation
- **THEN** they can find the Composer commands or scripts needed to install Laravel 12-compatible dependencies.

#### Scenario: Contributor wants Laravel 13 local validation

- **GIVEN** a contributor wants to validate against Laravel 13
- **WHEN** they read contributor documentation
- **THEN** they can find the Composer commands or scripts needed to install Laravel 13-compatible dependencies.