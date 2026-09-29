# Development Guidelines

## Tooling and Scope
- Run tools such as Pint, PHPStan, and Pest only on modified packages.
- If touching `packages/*/src/**`, run Pint only on the changed files or at least only on the changed packages.
- Never run Pint repo-wide "just to be safe"; it creates noisy diffs across unrelated packages.
- Prefer the standard project-local binaries directly (`./vendor/bin/pest`, `./vendor/bin/phpstan`, `./vendor/bin/rector`, `./vendor/bin/pint`) in a normal local shell.
- Do not add or commit machine-specific launcher files or symlinks such as `php-local`; personal PHP/Herd wrappers belong in local shell config, not the repository.
- Keep tracked agent and MCP config repo-safe. Local development credential files like `auth.json` may exist on your machine, but they must stay ignored and never be committed.
- Do not commit absolute home-directory paths, personal `SITE_PATH` values, or other machine-specific local tool wiring.

## Code Conventions
- Use `CarbonImmutable` or other immutable date/time objects wherever possible; avoid mutable `Carbon` unless you have a strong reason.

## Compatibility Policy
- Breaking changes are allowed when they improve the system. Backward compatibility is not required unless a task explicitly asks for it.
- Do not add backward-compatibility shims, legacy aliases, or deprecated-code paths; remove the old path instead of keeping both.
- Do not write data backfills or migrations that reinterpret legacy semantics; new columns start clean with no legacy null meaning.
