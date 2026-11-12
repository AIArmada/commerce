# Database Guidelines

## Primary Keys
- Use `uuid('id')->primary()` for primary keys.

## Foreign Key Columns
- Use `foreignUuid('col')` for foreign-key columns only.
- Do not add database foreign-key constraints.

## Integrity Rules
- Never add database-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- Enforce cascades and integrity in application logic through models, Actions, and services.
- No soft deletes: never use the `SoftDeletes` trait and never add `deleted_at` columns.

## Lifecycle Columns
- Lifecycle columns are always named `status`. The `state` column name is reserved for geographic address regions (province/state next to `city`/`postcode`) or for mirroring an external API field name.
- Record business-critical terminal transitions in dedicated `timestampTz` columns.
- Use `*_at` for the actual transition time and keep scheduled deadlines such as `expires_at` separate.
- Non-terminal states use toggle timestamps (set on entry, cleared on exit, e.g. `held_at`); terminal states record once and never clear.
- Binary active/inactive lifecycles may use a single toggle timestamp (`deactivated_at`/`hidden_at`) with a scope instead of a `status` column.
- Do not bury lifecycle events in JSON or booleans when the timestamp matters operationally.

## Migrations
- Keep migrations safe and idempotent.
- No `down()` method is required.
- Evolve schema by editing the owning migration in place; do not add a new migration file per schema change and do not drop and recreate tables to alter them.
- Do not write data backfills or migrations that reinterpret legacy semantics; new columns start clean with no legacy null meaning.

## JSON Columns
- JSON columns use the package's configured `json_column_type`; never hardcode `json` or `jsonb` in a migration.

## Verification
- Ensure no constraints or cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`
- Ensure no soft deletes slipped in: `rg -n -- "softDeletes\(|SoftDeletes" packages/*/database packages/*/src`
- Ensure JSON columns use package config: `rg -n -- "->json\(|->jsonb\(" packages/*/database`
