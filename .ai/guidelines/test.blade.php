# Testing Guidelines

- **Never run the whole Pest suite**; always run by package only (e.g., `./vendor/bin/pest --parallel tests/src/Inventory`). Identify the package you touched and target that package's tests.
- `--parallel` MUST be the first argument after `./vendor/bin/pest`.
- When many failures: capture once, group by cause, batch-fix, rerun targeted files (`--filter` when needed) before full package run.
- Coverage: use package-specific XML in `.xml/`; create if missing. Target ≥85% for non-Filament packages. Commands: `./vendor/bin/pest --parallel tests/src/PackageName`, `./vendor/bin/pest --parallel --coverage --configuration=.xml/package.xml`, `./vendor/bin/pest --parallel --coverage --min=85` when applicable.
