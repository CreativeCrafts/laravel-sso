# Outbound IdP Safety Spec Delta

## ADDED Requirements

### Requirement: Outbound IdP requests validate resolved addresses

The package SHALL validate outbound IdP URLs immediately before HTTP requests using both URL syntax checks and DNS-resolution-aware address checks.

#### Scenario: Public hostname is allowed

- **GIVEN** an IdP URL uses HTTPS
- **AND** its hostname resolves only to public routable addresses
- **WHEN** the package performs an outbound IdP HTTP request
- **THEN** the request is allowed.

#### Scenario: Hostname resolves to loopback

- **GIVEN** an IdP URL hostname resolves to `127.0.0.1` or `::1`
- **WHEN** the package performs an outbound IdP HTTP request
- **THEN** the request is rejected.

#### Scenario: Hostname resolves to private address

- **GIVEN** an IdP URL hostname resolves to an RFC1918, link-local, multicast, reserved, or metadata-service address
- **WHEN** the package performs an outbound IdP HTTP request
- **THEN** the request is rejected by default.

#### Scenario: Hostname has mixed answers

- **GIVEN** an IdP URL hostname resolves to both public and private/reserved addresses
- **WHEN** the package performs an outbound IdP HTTP request
- **THEN** the request is rejected.

#### Scenario: Hostname cannot be resolved

- **GIVEN** an IdP URL hostname has no DNS answers
- **WHEN** the package performs an outbound IdP HTTP request
- **THEN** the request is rejected by default.

### Requirement: Redirects are not allowed without revalidation

The package SHALL either disable redirects for IdP HTTP calls or revalidate every redirect target before following it.

#### Scenario: IdP endpoint redirects to private address

- **GIVEN** an IdP endpoint initially passes URL safety validation
- **AND** the HTTP response redirects to a private or reserved address
- **WHEN** redirects are processed
- **THEN** the redirected request is rejected or redirects are not followed.