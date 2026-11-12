# Model Guidelines

## Base Model Contract
- Use `Illuminate\Database\Eloquent\Concerns\HasUuids`.
- Do not declare `protected $table`; implement `getTable()` so the default table name resolves from package config (prefixable and remappable per package).
- When a model has self-referencing relations, `getTable()` must also honor per-instance overrides (`return $this->table ?? <config resolution>;`): Laravel aliases the inner table via `setTable()` for self-join existence queries, and ignoring the alias silently misbinds the join to the outer table.

## Type Safety
- Type relations and collections with PHPDoc generics.

## Relationship Behavior
- Implement application-level cascades in `booted()` using delete or null-out behavior.
- Never rely on database cascades.

## Lifecycle
- When a model has a status or state machine, keep the enum, transition code, and lifecycle columns in sync.
- Keep the state-to-timestamp mapping centralised in the transition method or supporting trait.
- Use immutable date casts for lifecycle timestamps when the model supports them.
- Lifecycle column naming and timestamp shape are defined in the database guidelines.
