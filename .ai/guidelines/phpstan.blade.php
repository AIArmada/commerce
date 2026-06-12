# PHPStan Guidelines

## Baseline
- Level 6.
- Analyse per package, for example `packages/<pkg>/src`, not repo-wide.

## Rules
- Respect `phpstan.neon`.
- Do not add new `ignoreErrors` entries unless root-cause fixes are exhausted.
- Prefer real fixes over suppression.

## Verification
- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`
