# Config Guidelines
- **Keys**: Keep minimal, remove unused (verify via grep).
- **Structure**:
  - Core: DB -> Creds -> Defaults -> Features -> Integrations -> HTTP -> Webhooks -> Cache -> Logging.
  - Filament: Nav -> Tables -> Features -> Resources.
- **Rules**:
  - Use `json_column_type` for JSON/Migration.
  - Prefer defaults over excessive `env()`.
  - Comments: Section headers only, inline for non-obvious.
