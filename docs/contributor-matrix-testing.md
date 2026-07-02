# Contributor Matrix Testing

This package supports multiple PHP and Laravel dependency sets. CI is the source of truth for full matrix coverage, but contributors can still reproduce individual dependency sets locally when needed.

## Supported matrix

The CI matrix validates checks and coverage for:

- PHP 8.3 with Laravel 12
- PHP 8.3 with Laravel 13
- PHP 8.4 with Laravel 12
- PHP 8.4 with Laravel 13
- PHP 8.5 with Laravel 12
- PHP 8.5 with Laravel 13

The runtime constraints are declared in `composer.json`:

```json
{
  "php": "^8.3",
  "illuminate/contracts": "^12.0|^13.0",
  "illuminate/database": "^12.0|^13.0",
  "illuminate/support": "^12.0|^13.0"
}
```

## Why local matrix testing is manual

Composer resolves one dependency set at a time. Testing Laravel 12 and Laravel 13 locally means intentionally changing the installed dependency set and regenerating `composer.lock`.

For that reason, this project does not provide Composer scripts that automatically mutate dependencies. Automated matrix validation belongs in CI, where each job runs in an isolated environment.

## Baseline local validation

For normal feature work, run the current dependency set:

```bash
composer install
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
vendor/bin/pest --coverage --coverage-text
```

Equivalent Composer scripts are also available:

```bash
composer validate-composer
composer security-audit
composer pint-test
composer analyse
composer test
composer test-coverage
```

## Testing Laravel 12 locally

Use this when you need to reproduce a Laravel 12-specific issue.

```bash
rm -rf vendor composer.lock
composer require --dev orchestra/testbench:^10.0 --no-update
composer require illuminate/contracts:^12.0 illuminate/database:^12.0 illuminate/support:^12.0 --no-update
composer update --with-all-dependencies
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
vendor/bin/pest --coverage --coverage-text
```

After testing, either restore your original branch state or reset dependency-file changes that were only made for local reproduction:

```bash
git checkout -- composer.json composer.lock
rm -rf vendor
composer install
```

## Testing Laravel 13 locally

Use this when you need to reproduce a Laravel 13-specific issue.

```bash
rm -rf vendor composer.lock
composer require --dev orchestra/testbench:^11.0 --no-update
composer require illuminate/contracts:^13.0 illuminate/database:^13.0 illuminate/support:^13.0 --no-update
composer update --with-all-dependencies
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
vendor/bin/pest --coverage --coverage-text
```

After testing, either restore your original branch state or reset dependency-file changes that were only made for local reproduction:

```bash
git checkout -- composer.json composer.lock
rm -rf vendor
composer install
```

## PHP version testing

Use the PHP version that matches the issue you are reproducing. Tools such as Docker, Herd, Valet, asdf, mise, or GitHub Codespaces can switch PHP versions locally.

When a PHP-version-specific issue is suspected, prefer opening a draft PR and letting CI run the isolated PHP/Laravel matrix before making broad dependency changes locally.

## Release rule

Do not treat a single local dependency set as release proof. A release branch is ready only when CI has passed checks and coverage for the full supported matrix.

## Documentation changes

When you add or rename files under `docs/`, update cross-links in `README.md` and run:

```bash
./vendor/bin/pest tests/Unit/DocumentationIntegrityTest.php
```

That test validates local markdown links across `README.md`, `SECURITY.md`, and every file in `docs/`.
