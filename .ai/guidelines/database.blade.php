# Database Guidelines
- **Primary keys**: `uuid('id')->primary()`.
- **Foreign keys**: `foreignUuid('col')` only.
- **Never** add DB-level constraints or cascades: no `->constrained()`, no `->cascadeOnDelete()`, no FK constraints.
- **Cascades/integrity**: enforce in application logic (models/actions/services).
- **Migrations**: keep safe/idempotent; no `down()` required.

## Verification
- Ensure no constraints/cascades slipped in: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`