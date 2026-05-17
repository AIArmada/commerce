# Testing Guidelines
- **Goal**: ELIMINATE BUGS.
- **Parallelism is mandatory**: Every Pest or PHPUnit test invocation must include `--parallel`.
  This applies to single files, directories, package sweeps, and final verification runs.
  If a command does not support `--parallel`, use the closest parallel-capable equivalent instead of omitting it.
- **Refs** (Filament v4 docs are acceptable for v5 testing APIs):
  - [Overview](https://filamentphp.com/docs/4.x/testing/overview)
  - [Resources](https://filamentphp.com/docs/4.x/testing/testing-resources)
  - [Tables](https://filamentphp.com/docs/4.x/testing/testing-tables)
  - [Schemas](https://filamentphp.com/docs/4.x/testing/testing-schemas)
  - [Actions](https://filamentphp.com/docs/4.x/testing/testing-actions)
  - [Notifications](https://filamentphp.com/docs/4.x/testing/testing-notifications)

## Execution
- **Do not run everything**. Run tests per package/scope.
- **Always use `--parallel`**. No exceptions.
- **Single**: `./vendor/bin/pest --parallel path/to/Test.php`
- **Dir**: `./vendor/bin/pest --parallel path/to/dir`
- **Full**: `./vendor/bin/pest --parallel ...` (final only)

## Coverage
- Always include `--parallel` when using `--coverage`.
- Command: `./vendor/bin/pest --coverage --parallel`
- Don’t run full coverage if `0% files > 10%`.
- Targets: Core ≥80%, Filament ≥70%, Support ≥80%.
- **Output**: ALWAYS pipe: `2>&1 | tee /tmp/out.txt`.
