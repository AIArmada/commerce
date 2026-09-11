# Persons Audit — DONE (2026-09-08)

## Verdict

The shared-identity package (`persons` + `filament-persons`) has passed
full review and implementation. Slug/searchable generation, fail-closed
country/institution reference validation, `PersonStatus` lifecycle,
pure formatted-name access, issuer/bio invariants, gapless reorder,
config discipline, and Filament ID revalidation are all in place —
with zero rated findings remaining. `Person` remains deliberately
global/shared per the documented topology; tenant-scoping it would be
a different project, not a leftover.

## What was done

- **Fail-closed references:** `PersonsModelReferenceGuard` centralizes
  country/institution validation (mirrors contacting's guard, no DB
  FKs) — see `code-fixes-record.md`.
- **Lifecycle:** `PersonStatus` enum + transition centralizing
  timestamp mapping; pure formatted-name accessor (no `setRelation`);
  bio shape validation; `TitleIssuer` saving invariant — see
  `code-fixes-record.md`.
- **Reorder:** transactional gapless normalization in
  `ReorderTitleAction` — see `code-fixes-record.md`.
- **Config + Filament:** prefix env wiring, addressing default to
  `class_exists`, institution fail-closed; submitted-ID revalidation
  + eager loads killing the accessor N+1 — see `code-fixes-record.md`.
- **Creation actions:** `CreatePersonAction`, `AssignTitleAction`,
  `AssignCredentialAction` (idempotent) — see `code-fixes-record.md`.
- Suites: Persons 28 passed (94 assertions), FilamentPersons 3 passed
  (7 assertions); PHPStan level 6 clean. No migration required.

## Residual notes

- Physical index batch implemented (`2026_09_11_000001`: conditional
  slug/primary uniques + covering indexes); app-level mitigations
  (collision-budget slug loop, `lockForUpdate` demotion) remain as
  defense in depth.
- `EventOrganizer`/venue identity settled in the events track
  (`EventOrganizer` documented as event-scoped role; see `events.md`,
  now DONE).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
