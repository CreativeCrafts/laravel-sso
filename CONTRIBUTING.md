# Contributing

Thank you for considering a contribution to `creativecrafts/laravel-sso`.

## Development setup

Install dependencies:

```bash
composer install
```

Run the full local validation set:

```bash
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
vendor/bin/pest --coverage --coverage-text
```

CI is the source of truth for the supported PHP and Laravel matrix. See `docs/contributor-matrix-testing.md` when you need to reproduce a specific Laravel 12, Laravel 13, PHP 8.3, PHP 8.4, or PHP 8.5 dependency set locally.

## Pull request expectations

Keep pull requests focused and reviewable.

A pull request should include:

- a clear summary of the change
- tests for behavior changes
- documentation updates for public behavior, configuration, schema, or operational changes
- upgrade-guide notes for release-sensitive changes

Do not include generated vendor files, local caches, or unrelated formatting churn.

## Architecture expectations

Prefer existing package seams:

- contracts for host-application extension points
- services for protocol and lifecycle behavior
- repositories for persistence concerns
- DTOs for predictable data transfer

Avoid adding hidden service-locator dependencies in reusable classes. Prefer explicit constructor dependencies or validation-rule seams.

## Security defaults

Preserve conservative defaults unless a design proposal explicitly changes them.

Defaults should remain:

- provisioning denied unless explicitly enabled
- identity linking denied unless explicitly enabled
- audit context redacted unless explicitly extended
- raw claims not persisted unless explicitly enabled
- IdP URLs production-safe unless local-development overrides are enabled
- callback state replay-safe

## Documentation

When updating README links, add or update the target file and keep the README link integrity test passing.

When changing upgrade-sensitive behavior, update `docs/upgrade-guide.md`.

## OpenSpec changes

For broad behavior, architecture, or security hardening changes, create or update an OpenSpec proposal before implementation. Keep task checkboxes accurate as work is completed.
