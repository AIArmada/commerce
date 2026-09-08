# Contacting Audit — DONE (2026-09-08)

## Verdict

The contact-points package (`contacting` + `filament-contacting`)
has passed full review and implementation. Alias removed, snapshots
fail-closed and transactional, privacy defaults channel-aware, demote
paths scoped, exporters owner-scoped, and real coverage exists at the
repo-root suites — with zero rated findings remaining.

## What was done

- **Alias deleted:** `order_column` get/set overrides + fillable
  entries removed from both models (zero callers repo-wide); canonical
  `sort_order` only — see `code-fixes-record.md`.
- **Snapshot hardening:** fail-closed when disabled (no phantom
  return), transactional `fromBundle`, source-vs-snapshotable owner
  mismatch rejection, env-wired flag — see `code-fixes-record.md`.
- **Privacy:** channel-aware `is_public` defaults (PII channels
  private) without backfill — see `code-fixes-record.md`.
- **Scoping:** owner scope on demote queries with guard-ordering
  pin; `OwnerUiScope` in exporters; per-row importer guards with
  cross-owner regression — see `code-fixes-record.md`.
- Suites: Contacting 352 passed (491 assertions),
  FilamentContacting 10 passed + 4 skipped (38 assertions); PHPStan
  level 6 clean. No migration required.

## Residual notes

- Timestamp precision (`timestamps()` vs `timestampsTz()`) aligned on
  next table touch only — harmless mixed precision, not worth a
  migration alone.
- `contacting` stays a hard `customers` dependency by ratified policy
  (canonical-infrastructure doctrine), overriding the old demote
  recommendation.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
