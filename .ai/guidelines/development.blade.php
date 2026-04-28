# Development Guidelines
- **Safety**: NEVER "cleanup" or mass-revert without permission.
- **Scope**: Run tools (Pint/PHPStan) ONLY on modified packages.

## Monorepo Formatting
- **Golden rule**: No style-only PRs.
- If touching `packages/*/src/**`, run Pint only on changed files (or at least only the changed packages).
- Never run Pint repo-wide “just to be safe” — it creates noisy diffs across unrelated packages.

## Best Practices
- **Strict Laravel**: `Arr::get()`, `Collections`, `Service Container`.
- **Modern PHP**: 8.4+ (readonly, match, modern typing).
- **Time**: Use `CarbonImmutable` (or immutable date/time objects) wherever possible; avoid mutable `Carbon` unless you have a strong reason.
- **Octane-safe by default**: Avoid process-wide mutable statics/singletons for request data; use request attributes, scoped container bindings, or explicit context wrappers that always restore state.
- **Logic**: Action Classes only. No logic in Controllers/Models.
- **Structure**: SOLID, Repository for access, Factory for creation.

## Naming
- **Classes**: `PascalCase`.
- **Methods/Vars**: `camelCase`.
- **Consts**: `SCREAMING_SNAKE`.
- **DB**: `snake_case` (tables/cols).
- **Bool**: `is_`, `has_`, `can_`.

## Agents
- **Auditor**: Strict auditing/security (`.github/agents/Auditor.agent.md`).
- **QC**: QA/Testing (`.github/agents/QC.agent.md`).
- **Visionary**: Architecture (`.github/agents/Visionary.agent.md`).

## Beta Status
- **Break Changes**: Allowed for improvement. No backward compatibility required.