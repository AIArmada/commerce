# Audit Scope and Method

## Session metadata

- **Date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Branch:** main
- **Working tree:** clean (one untracked file: `AUDIT.md`)
- **Environment:** macOS (Darwin), PHP 8.5.7, Composer 2.9.5, Node 22.23.1

## Operating variables

| Variable | Value |
|----------|-------|
| Repository root | `/Users/Saiffil/Herd/commerce` |
| Audit output directory | `docs/audit` |
| Allow code changes | false |
| Allow dependency installation | false |
| Allow network access | false |
| Allow destructive commands | false |
| Run existing tests | true |
| Run existing static analysis | true |
| Run existing build commands | true |
| Audit depth | exhaustive |

## Exclusions

- Network access is not permitted, so vulnerability database lookups and remote scans are blocked.
- Dependency installation is not permitted, so packages that need `composer install` to resolve are assessed from lock-file state only.

## Method

This audit follows the process defined in `AUDIT.md` (root). Each phase creates or updates files under `docs/audit/`. Package audits are conducted one per execution using `08-AUDIT-PROGRESS.md` as persistent state.
