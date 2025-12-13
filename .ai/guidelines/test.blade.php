# Testing Guidelines

- **Never run the whole Pest suite**; always run by package only (e.g., `./vendor/bin/pest --parallel tests/src/Inventory`). Identify the package you touched and target that package's tests.
- `--parallel` MUST be the first argument after `./vendor/bin/pest`.
- When many failures: capture once, group by cause, batch-fix, rerun targeted files (`--filter` when needed) before full package run.
- When working on coverage targets, create as many tests as possible before running coverage. Coverage runs are expensive; batching tests first is significantly faster.
- When you do run coverage, capture the output and list the under-covered classes/files to target next, so you don’t have to run coverage again just to discover what needs tests.
- Coverage: use package-specific XML in `.xml/`; create if missing. Target ≥85% for non-Filament packages. Commands: `./vendor/bin/pest --parallel tests/src/PackageName`, `./vendor/bin/pest --parallel --coverage --configuration=.xml/package.xml`, `./vendor/bin/pest --parallel --coverage --min=90` when applicable.
