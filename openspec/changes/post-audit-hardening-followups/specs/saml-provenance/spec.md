# SAML Provenance Spec Delta

## ADDED Requirements

### Requirement: SAML validation exposes signed-element provenance

The package SHALL expose which SAML response and/or assertion element was validated by XML signature verification.

#### Scenario: Response signature validates

- **GIVEN** a SAML response with a valid response-level signature
- **WHEN** signature validation succeeds
- **THEN** the validation result identifies the signed response ID
- **AND** marks response signature validation as successful.

#### Scenario: Assertion signature validates

- **GIVEN** a SAML response with a valid assertion-level signature
- **WHEN** signature validation succeeds
- **THEN** the validation result identifies the signed assertion ID
- **AND** marks assertion signature validation as successful.

### Requirement: Claims extraction uses validated signature context

The package SHALL extract SAML claims from the validated DOM/signature context rather than reparsing raw XML after signature validation.

#### Scenario: Assertion signature binds extraction

- **GIVEN** a SAML response with a valid assertion signature
- **WHEN** claims are normalized
- **THEN** claims are extracted only from the signed assertion identified by validation provenance.

#### Scenario: Response signature binds extraction

- **GIVEN** a SAML response with a valid response signature
- **WHEN** claims are normalized
- **THEN** claims are extracted only from the assertion directly under the signed response identified by validation provenance.

#### Scenario: Unsigned sibling assertion is present

- **GIVEN** a malicious SAML response contains an unsigned sibling assertion
- **WHEN** signature validation and claims normalization run
- **THEN** unsigned sibling claims are not extracted
- **AND** the response is rejected when document-shape rules are violated.

### Requirement: Conditions validation uses trusted context

The package SHALL validate SAML audience, recipient, destination, and correlation conditions against the same validated SAML context used for claims extraction.

#### Scenario: Conditions are evaluated on trusted context

- **GIVEN** a signed SAML response or assertion
- **WHEN** conditions validation runs
- **THEN** the condition nodes are selected from the validated context
- **AND** unsigned alternate condition nodes cannot influence validation.