# PHPStan Guidelines

- All code must pass PHPStan level 6.
- **Never run PHPStan on the whole `packages` directory.** Run it per package you changed (e.g., `./vendor/bin/phpstan analyse --level=6 packages/inventory`).
- Verify with the per-package command (`phpstan.neon` baseline applies).

## Baseline discipline (strict)

- Do **not** add new `ignoreErrors` entries or widen `excludePaths` unless you have exhausted reasonable fixes and can justify why the remaining issue is not safely fixable right now.
- Prefer fixing root causes (types, generics, nullability, dead code, missing assertions) over suppressing.
- During development/auditing/planning/execution, proactively try to **reduce** existing `ignoreErrors`/`excludePaths` gradually (delete or narrow them) while keeping targeted tests passing.
- Any unavoidable ignore must be:
	- narrowly scoped (specific message + path),
	- documented in the PR/notes with the fix attempt summary,
	- and treated as temporary debt to remove soon.
