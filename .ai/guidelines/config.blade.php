# Config Guidelines
- **Keys**: Keep minimal. If a key is defined but never read, remove it.
- **Section order** (keep consistent across packages):
  - Core: Database -> Credentials/API -> Defaults -> Features/Behavior -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
  - Filament: Navigation -> Tables -> Features -> Resources.
- **Rules**:
  - Any package that uses JSON columns in migrations MUST define and use a `json_column_type` setting.
  - Prefer opinionated defaults over excessive `env()` usage (only use env vars for secrets or deploy-time values).
  - Comments: section headers only; inline comments only for non-obvious values.

## Verification
- Find config reads: `rg -n -- "config\('" packages/*/src packages/*/config`
- Find unused keys (typical pattern): `rg -n -- "config\('pkg\." packages/*/config | cat`
