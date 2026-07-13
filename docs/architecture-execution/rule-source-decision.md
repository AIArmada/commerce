# Decision Record: Repository Rule Source

- **Task:** GOV-002
- **Date:** 2026-07-12
- **Status:** Approved
- **Decision:** `.ai/guidelines/` is the single canonical source of repository rules.

## Problem

AGENTS.md:570 referenced `.ai/rules/index.md` and instructed every agent to open it before editing. That path never existed in the repository. The actual rule content lives in `.ai/guidelines/*.blade.php` (13 topic-based files). Every source edit was therefore non-compliant with the repository's own mandatory preparation rule.

## Observed facts

1. `.ai/rules/` directory does not exist and has no git history.
2. `.ai/guidelines/` contains 13 committed rule files: `00-overview`, `config`, `database`, `development`, `docs`, `filament`, `general`, `model`, `multitenancy`, `packages`, `phpstan`, `spatie`, `test`.
3. AGENTS.md inlines the full content of all 13 guideline files under `<laravel-boost-guidelines>` tags with `=== .ai/<topic> rules ===` section markers.
4. `EnsureCustomGuidelinesSymlinkAction` (packages/commerce-support) symlinks `.ai/guidelines` into testbench for test runs — confirming `.ai/guidelines` is the established operational path.
5. The `.ai/rules` reference appeared in exactly one place: AGENTS.md:570, inside the `=== boost rules ===` section (Laravel Boost boilerplate).
6. No CI, tooling, or package code references `.ai/rules`.

## Decision

**Update AGENTS.md to reference `.ai/guidelines/` as the canonical rule source.**

The `.ai/guidelines/*.blade.php` files are source artifacts (committed Markdown content in Blade templates). They are consumed by:
- AGENTS.md (inlined under `<laravel-boost-guidelines>`)
- `EnsureCustomGuidelinesSymlinkAction` (testbench symlink)
- Agents reading them directly before edits

## Rejected alternatives

### Alternative A: Create `.ai/rules/index.md` mapping globs to guideline files

Rejected. This would add a second hierarchy that duplicates the topic-based structure already in `.ai/guidelines/00-overview.blade.php`. The guideline files are topic-named (config, database, filament, etc.), not glob-mapped. An index file would be a redirect to content that already has a clear entry point. Two rule directories would violate "exactly one canonical source."

### Alternative B: Rename `.ai/guidelines/` to `.ai/rules/`

Rejected. `EnsureCustomGuidelinesSymlinkAction` and its tests depend on the `.ai/guidelines` path. Renaming would break testbench setup and require coordinated changes across packages for zero functional gain. The path name is an implementation detail; the problem was the dead reference, not the directory name.

## Changes made

1. **AGENTS.md:570** — replaced all `.ai/rules` references with `.ai/guidelines`, pointing to `00-overview.blade.php` as the entry point. Changed "file globs" language to "topic" to match the actual topic-based structure.

## Verification

- `rg -n '\.ai/rules' AGENTS.md` returns no results.
- `test -d .ai/guidelines` passes.
- `test -f .ai/guidelines/00-overview.blade.php` passes.
- No rule content was added, removed, or modified — only the dead reference path was corrected.

## Guidance for later tasks

All later task briefs should reference `.ai/guidelines/` as the canonical rule source. The `=== .ai/<topic> rules ===` sections already inlined in AGENTS.md are rendered views of these files.
