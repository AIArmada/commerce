# Development Guidelines

## Tooling and Scope
- Run tools such as Pint, PHPStan, and Pest only on modified packages.
- If touching `packages/*/src/**`, run Pint only on the changed files or at least only on the changed packages.
- Never run Pint repo-wide "just to be safe"; it creates noisy diffs across unrelated packages.
- Do not open style-only PRs.
- Prefer the standard project-local binaries directly (`./vendor/bin/pest`, `./vendor/bin/phpstan`, `./vendor/bin/rector`, `./vendor/bin/pint`) in a normal local shell.
- Do not add or commit machine-specific launcher files or symlinks such as `php-local`; personal PHP/Herd wrappers belong in local shell config, not the repository.
- Keep tracked agent and MCP config repo-safe. Local development credential files like `auth.json` may exist on your machine, but they must stay ignored and never be committed.
- Do not commit absolute home-directory paths, personal `SITE_PATH` values, or other machine-specific local tool wiring.

## Code Conventions
- Prefer Laravel-native helpers, collections, and the service container when the framework already provides the right abstraction.
- Use modern PHP 8.4 features and explicit typing.
- Use `CarbonImmutable` or other immutable date/time objects wherever possible; avoid mutable `Carbon` unless you have a strong reason.
- Keep business logic out of controllers and models. Put orchestration in Actions.
- Use SOLID principles, repositories for data access, and factories for object creation when those abstractions improve clarity.

## Naming
- Classes: `PascalCase`.
- Methods and variables: `camelCase`.
- Constants: `SCREAMING_SNAKE`.
- Database tables and columns: `snake_case`.
- Boolean names: `is_`, `has_`, `can_`.

## Team Roles
- Auditor: strict auditing/security (`.github/agents/Auditor.agent.md`).
- QC: QA/testing (`.github/agents/QC.agent.md`).
- Visionary: architecture (`.github/agents/Visionary.agent.md`).

## Compatibility Policy
- Breaking changes are allowed when they improve the system. Backward compatibility is not required unless a task explicitly asks for it.
