# Database Guidelines

## Primary Keys
- Use `uuid('id')->primary()` for primary keys.

## Foreign Key Columns
- Use `foreignUuid('col')` for foreign-key columns only.
- Do not add database foreign-key constraints.

## Integrity Rules
- Never add database-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- Enforce cascades and integrity in application logic through models, Actions, and services.

## Migrations
- Keep migrations safe and idempotent.
- No `down()` method is required.

## Verification
- Ensure no constraints or cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`
