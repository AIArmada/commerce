# Config Guidelines

## Key Discipline
- Keep config keys minimal. If a key is defined but never read, remove it.
- Prefer opinionated defaults over excessive `env()` usage. Use env vars only for secrets or deploy-time values.
- Comments should be section headers only; inline comments are only for non-obvious values.

## Section Order
- Core packages: Database -> Credentials/API -> Defaults -> Features/Behavior -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
- Filament packages: Navigation -> Tables -> Features -> Resources.

## JSON Columns
- Any package that uses JSON columns in migrations must define and use a `json_column_type` setting so the column type stays configurable.

## Verification
- Find config reads: `rg -n -- "config\('" packages/*/src packages/*/config`
- Find unused keys (typical pattern): `rg -n -- "config\('pkg\." packages/*/config | cat`
