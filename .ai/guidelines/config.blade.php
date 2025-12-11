# Config Guidelines

- Only keep config keys that are used in code.
- Order core package configs: Database → Credentials/API → Defaults → Features/Behavior → Integrations → HTTP → Webhooks → Cache → Logging.
- Order Filament configs: Navigation → Tables → Features → Resources.
- Keep configs minimal; publish only what is needed; nest related settings.
- Migrations with JSON columns require a `json_column_type` config key.
- Prefer defaults over excess env() wrappers; remove unused keys.
- Comments: Laravel-style section headers only; inline comments only for non-obvious values.
- Verify with `grep -r "config('package.key')" src/ packages/*/src/`; remove keys with no matches.
