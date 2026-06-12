# Testing Guidelines

## Goal
- Eliminate bugs.

## Parallelism
- Every Pest or PHPUnit invocation must include `--parallel`.
- This applies to single files, directories, package sweeps, and final verification runs.
- If a command does not support `--parallel`, use the closest parallel-capable equivalent instead of omitting it.

## References
- [Overview](https://filamentphp.com/docs/5.x/testing/overview)
- [Resources](https://filamentphp.com/docs/5.x/testing/testing-resources)
- [Tables](https://filamentphp.com/docs/5.x/testing/testing-tables)
- [Schemas](https://filamentphp.com/docs/5.x/testing/testing-schemas)
- [Actions](https://filamentphp.com/docs/5.x/testing/testing-actions)
- [Notifications](https://filamentphp.com/docs/5.x/testing/testing-notifications)

## Execution
- Do not run everything. Run tests per package or scope.
- Single file: `./vendor/bin/pest --parallel path/to/Test.php`
- Directory: `./vendor/bin/pest --parallel path/to/dir`
- Full suite: `./vendor/bin/pest --parallel ...` only for final verification.

## Coverage
- Always include `--parallel` when using `--coverage`.
- Command: `./vendor/bin/pest --coverage --parallel`
- Do not run full coverage if `0% files > 10%`.
- Targets: Core >=80%, Filament >=70%, Support >=80%.
- Always pipe output with `2>&1 | tee /tmp/out.txt`.
