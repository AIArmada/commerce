# Addressing Audit — DONE (2026-09-08)

## Verdict

The canonical-address package (`addressing` + `filament-addressing`)
has passed full review and implementation. Full model resolution,
canonical-only aliases, FK-authoritative normalization, provider
validation, snapshot reason contract, single table-name resolver for
runtime and all 19 migrations, and the customers pilot live — with
zero rated findings remaining.

## What was done

- **Full `ModelResolver`:** country/state/city/area/address/snapshot
  classes — see `code-fixes-record.md`.
- **Canonical aliases:** `lat`/`lng`/`google_place_id` deleted;
  callers on canonical names — see `code-fixes-record.md`.
- **Normalization:** FK-authoritative convention, real normalization
  routing, provider boot validation — see `code-fixes-record.md`.
- **Snapshot reasons:** blank values rejected — see
  `code-fixes-record.md`.
- **Resolver everywhere:** `AddressingTableResolver` serves runtime +
  all 19 migrations; zero flat-key reads — see `code-fixes-record.md`.
- **`ResolvesAddressingResources` falsified:** verified adapter-only
  UI/policy seam with no core duplication — merge dropped as false
  positive, split documented — see `code-fixes-record.md`.
- Suites: Addressing 187 passed (436 assertions),
  FilamentAddressing 43 passed (68 assertions); PHPStan level 6
  clean. No migration required (resolver adoption is code-only).

## Residual notes

- Timestamp/index micro-improvements deferred — the dev-only rule
  permits a batch as a follow-up.
- Orders pilot implemented end-to-end (write path, consumers,
  invoice reads, composer contract); events adoption is partially done
  (`Venue` and `EventLocation` on `HasAddresses` since 2026-09-12, legacy
  columns dropped) with the remaining non-owner models closed as
  won't-do by design (shared catalog, correctly unowned — events track).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
