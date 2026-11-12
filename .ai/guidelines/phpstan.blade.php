# PHPStan Guidelines

## Baseline
- Level 6.
- Analyse per package, for example `packages/<pkg>/src`, not repo-wide.

## Rules
- Do not add new `ignoreErrors` entries unless root-cause fixes are exhausted.

## Runtime Extensions
- Before removing a method that static analysis reports as undefined, verify runtime extension sources first.
- Check `macro()` / `hasMacro()` registrations, package mixins or traits, and framework or plugin extension points.
- If the method is runtime-provided, preserve behavior and fix analysis with a narrow, targeted change.

## Verification
- Example: `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`
