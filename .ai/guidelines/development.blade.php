# Development Guidelines
- **Safety**: NEVER "cleanup" or mass-revert without permission.
- **Scope**: Run tools (Pint/PHPStan) ONLY on modified packages.

## Best Practices
- **Strict Laravel**: `Arr::get()`, `Collections`, `Service Container`.
- **Modern PHP**: 8.2+ (readonly, match).
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