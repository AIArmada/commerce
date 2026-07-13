# Contributing

## Dependency changes

Before submitting any change to `composer.json` or `composer.lock`, run:

```bash
composer install --no-interaction
composer audit --locked --no-interaction
```

Commit the lockfile produced by the intended dependency operation. Do not bypass advisory failures, add `continue-on-error`, or make the audit job optional. A temporary advisory exception must follow the owner, justification, compensating-control, expiry, and review process in `SECURITY.md`.

## Verification

Use PHP 8.4. Run affected package tests with Pest in parallel, then package-scoped Pint, Rector dry-run, and PHPStan level 6.
