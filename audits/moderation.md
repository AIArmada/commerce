# Moderation Audit — DONE (2026-09-09)

## Verdict

The moderation package (`moderation`, no adapter by design) has
passed full review and implementation. Blocks actually expire,
owner config is standard, legacy validators gone, transitions
centralized, dead helpers deleted — with zero rated findings
remaining.

## What was done

- **Expiry:** active/expired scopes, centralized transitions,
  owner-aware chunked sweep + command — see `code-fixes-record.md`.
- **Config:** standard sibling owner-config path adopted — see
  `code-fixes-record.md`.
- **Validators:** legacy `scopeForOwner` duck-type removed (zero
  callers repo-wide) — see `code-fixes-record.md`.
- **Hygiene:** dead helpers deleted (zero callers) — see
  `code-fixes-record.md`.
- Suites: Moderation 61 passed (256 assertions); PHPStan level 6
  clean. No migration required.

## Residual notes

- None open (root `TestCase.php` now uses the canonical key — see
  `code-fixes-record.md`).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
