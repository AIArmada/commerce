# Model Guidelines
- **Base**:
  - Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
  - Do NOT set `protected $table`; implement `getTable()` using package config (tables map + prefix).
- **Relations**: type relations and collections with PHPDoc generics.
- **Cascades**: implement application-level cascades in `booted()` (delete or null-out). Never rely on DB cascades.
- **Migrations**: use `foreignUuid()` only (no `constrained()` / FK constraints).

## Verification
- Search for forbidden DB cascades/constraints in migrations: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`