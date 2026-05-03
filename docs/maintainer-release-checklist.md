# Maintainer Release Checklist

Use this checklist before tagging a release of `creativecrafts/laravel-sso`.

## Scope

- Confirm merged changes match the intended milestone.
- Confirm OpenSpec task checkboxes are accurate.
- Confirm behavior changes are reflected in `docs/upgrade-guide.md`.
- Confirm README examples still match current configuration and routes.
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
- upgrade notes cover migration and rollout requirements.

## Documentation review

Confirm README-linked docs exist:

- `docs/getting-started.md`
- `docs/operator-guide.md`
- `docs/error-catalog.md`
- `docs/deployment-guide.md`
- `docs/troubleshooting.md`
- `docs/upgrade-guide.md`
- `docs/maintainer-release-checklist.md`
- `docs/architecture-audit.md`

Run the README link integrity test before release.

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
2. Confirm installation in a clean Laravel app.
3. Run a minimal OIDC smoke test.
4. Run a minimal SAML smoke test when feasible.
5. Monitor issues and CI for regressions.
