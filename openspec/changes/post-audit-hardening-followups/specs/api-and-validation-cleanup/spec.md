# API and Validation Cleanup Spec Delta

## ADDED Requirements

### Requirement: Admin validation dependencies are explicit

The package SHALL avoid hidden service-locator dependencies inside admin configuration validation logic.

#### Scenario: IdP URL validation runs in admin request validation

- **GIVEN** an admin request validates OIDC or SAML IdP URLs
- **WHEN** validation needs URL trust policy behavior
- **THEN** the dependency is provided through an explicit validation rule or validator service seam
- **AND** validation does not call the global service container from a reusable trait.

### Requirement: Auth-attempt lifecycle API semantics are documented

The package SHALL document public auth-attempt lifecycle methods whose use can bypass preferred protocol-validation sequencing.

#### Scenario: Developer reads `consumeByState()` contract

- **GIVEN** a developer inspects `AuthAttemptService::consumeByState()`
- **WHEN** they read its PHPDoc
- **THEN** they are warned that the method consumes an attempt directly
- **AND** internal callback flows prefer reserve/validate/mark-consumed sequencing.

### Requirement: Failed auth-attempt status semantics are unambiguous

The package SHALL make failed validation semantics unambiguous in code and documentation.

#### Scenario: Validation failure is retryable

- **GIVEN** callback protocol validation fails
- **WHEN** the attempt is released for retry
- **THEN** the code documents whether `status` returns to `pending` and `failed_at` records the last failure
- **AND** unused terminal failure constants are removed or assigned a concrete behavior.

### Requirement: Optional OIDC temporal claims are type-safe when present

The package SHALL reject optional temporal OIDC claims when they are present with invalid types.

#### Scenario: `iat` is malformed

- **GIVEN** an ID token includes `iat`
- **AND** `iat` is not numeric
- **WHEN** ID token validation runs
- **THEN** validation fails closed.

#### Scenario: `nbf` is malformed

- **GIVEN** an ID token includes `nbf`
- **AND** `nbf` is not numeric
- **WHEN** ID token validation runs
- **THEN** validation fails closed.

### Requirement: Redirect edge-case behavior is covered

The package SHALL have regression coverage for encoded or ambiguous redirect values.

#### Scenario: Encoded protocol-relative path is supplied

- **GIVEN** a redirect target such as `/%2F%2Fevil.example`
- **WHEN** callback redirect resolution runs
- **THEN** the package behavior is explicitly tested and documented through the expected assertion.

#### Scenario: Encoded backslash path is supplied

- **GIVEN** a redirect target such as `/%5Cevil.example`
- **WHEN** callback redirect resolution runs
- **THEN** the package behavior is explicitly tested and documented through the expected assertion.

#### Scenario: Control-character redirect is supplied

- **GIVEN** a redirect target containing a literal or decoded ASCII control character
- **WHEN** callback redirect resolution runs
- **THEN** the target is rejected.