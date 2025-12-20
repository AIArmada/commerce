# PHPStan Guidelines
- **Level**: 6
- **Scope**: per package (e.g. `packages/<pkg>/src`), not repo-wide.
- **Rules**:
  - Respect `phpstan.neon` / `phpstan-baseline.neon`.
  - Do not add new `ignoreErrors` unless root-cause fixes are exhausted.
  - Prefer real fixes over suppression.

## Verification
- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`