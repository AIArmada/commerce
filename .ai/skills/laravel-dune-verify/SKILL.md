---
name: laravel-dune-verify
description: Verify changed Commerce packages and paired Filament adapters using scoped Pest, PHPStan, Pint, and observable runtime evidence.
---

# Laravel Dune — Package Verification

Read `CONTEXT-MAP.md`, the owning `packages/<pkg>/CONTEXT.md`, its canonical docs, and matching `.ai/rules/index.md` entries before discovery or edits. Use graph tools for code discovery. Follow the root [Dune guideline](../../guidelines/laravel-dune.blade.php) and [verification rule](../../rules/verification-and-hygiene.md).

1. Identify modified packages and affected consumers. Locate test paths from root/package Composer mappings and test configuration; tests commonly live under `tests/src/<Namespace>/`.
2. Define proof for the changed behavior: returned values, persisted state, authorization/owner isolation, failure rollback, or retry effects as applicable. Prefer existing tests over new scripts.
3. Run changed-file Pint when PHP changes. Run `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6` for affected package source. Run `./vendor/bin/pest --parallel <affected-test-path>` for affected tests. Substitute real paths; use shell `pipefail` and pipe output through `2>&1 | tee /tmp/<task-specific-log>.txt`.
4. Include the paired Filament adapter when a changed core API affects it. Use the existing demo/test host only when runtime proof is needed beyond tests, with isolated test data and explicit owner context.
5. For guidance-only changes, check frontmatter, rule-index agreement, relative links, and obsolete path/command references instead of running unrelated PHP tests.

Complete when required scoped checks pass and observed behavior meets the success criteria. Report failures and unrun checks precisely. Existing repository tooling is the source of truth for available commands; add harness files or documentation only when requested.
