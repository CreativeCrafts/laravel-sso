# Maintainer Release Checklist

Use this checklist before tagging a release of `creativecrafts/laravel-sso`.

## Scope

- Confirm merged changes match the intended milestone.
- Confirm OpenSpec task checkboxes are accurate (when using OpenSpec — see [CONTRIBUTING](../CONTRIBUTING.md)).
- Confirm behavior changes are reflected in [Upgrade Guide](upgrade-guide.md) and [CHANGELOG](../CHANGELOG.md).
- Confirm README and [Getting Started](getting-started.md) examples match current commands, publish tags, and routes.
- Confirm public contract changes are intentional.

## Local validation

Run:

```bash
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
vendor/bin/pest --coverage --coverage-text
```

Or:

```bash
composer ci
```

For local reproduction of a specific dependency set, use [Contributor Matrix Testing](contributor-matrix-testing.md).

## CI matrix

CI is the source of truth for dependency-set validation. The release branch must pass checks and coverage for:

- PHP 8.3 with Laravel 12
- PHP 8.3 with Laravel 13
- PHP 8.4 with Laravel 12
- PHP 8.4 with Laravel 13
- PHP 8.5 with Laravel 12
- PHP 8.5 with Laravel 13

Do not tag a release from a failing or incomplete CI run.

## Package behavior review

Before release, confirm:

- OIDC outbound URL validation remains fail-closed.
- SAML signature validation remains strict.
- protocol payloads are not persisted by default.
- audit context remains redacted by default.
- provisioning and identity linking remain deny-by-default.
- callback state remains replay-safe.
- connection/IdP ULIDs are used in public URL generation.
- external identity claim encryption default remains on.
- upgrade notes cover migration and rollout requirements.

## Documentation review

Confirm these files exist and are linked from [README](../README.md):

- [Getting Started](getting-started.md)
- [Configuration Reference](configuration-reference.md)
- [Integration Guide](integration-guide.md)
- [Admin API](admin-api.md)
- [Operator Guide](operator-guide.md)
- [Deployment Guide](deployment-guide.md)
- [Security Guide](security.md)
- [Troubleshooting Guide](troubleshooting.md)
- [Error Catalog](error-catalog.md)
- [Upgrade Guide](upgrade-guide.md)
- [Auth Attempt Lifecycle](auth-attempt-lifecycle.md)
- [Architecture Audit](architecture-audit.md)
- [Contributor Matrix Testing](contributor-matrix-testing.md)
- [Maintainer Release Checklist](maintainer-release-checklist.md)

Run documentation link integrity tests:

```bash
vendor/bin/pest tests/Unit/DocumentationIntegrityTest.php
```

## Release notes

Include:

- new behavior
- hardening changes
- migration requirements
- configuration changes
- compatibility matrix
- deprecations or behavior changes
- known limitations

## After publishing

1. Confirm Packagist update.
2. Confirm installation in a clean Laravel app (`composer require`, `sso:install --run-migrations`).
3. Run `php artisan sso:doctor --strict`.
4. Run a minimal OIDC smoke test.
5. Run a minimal SAML smoke test when feasible.
6. Monitor issues and CI for regressions.
