# Model Guidelines

## Base Model Contract
- Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Do not set `protected $table`; implement `getTable()` using package config so table names can be prefixed and remapped per package.

## Type Safety
- Type relations and collections with PHPDoc generics.

## Relationship Behavior
- Implement application-level cascades in `booted()` using delete or null-out behavior.
- Never rely on database cascades.

## Lifecycle
- When a model has a status or state machine, keep the enum, transition code, and lifecycle columns in sync.
- Record business-critical terminal transitions in dedicated `timestampTz` columns.
- Use `*_at` for the actual transition time and keep scheduled deadlines such as `expires_at` separate.
- Do not bury lifecycle events in JSON or booleans when the timestamp matters operationally.
- Keep the state-to-timestamp mapping centralised in the transition method or supporting trait.
- Use immutable date casts for lifecycle timestamps when the model supports them.

## Verification
- Search for forbidden DB cascades or constraints in migrations: `rg -n -- "constrained\(|cascadeOnDelete\(" packages/*/database`
