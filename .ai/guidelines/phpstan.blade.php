# PHPStan Guidelines

## Baseline
- Level 6.
- Analyse per package, for example `packages/<pkg>/src`, not repo-wide.

## Rules
- Do not add new `ignoreErrors` entries unless root-cause fixes are exhausted.

## Verification
- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`
