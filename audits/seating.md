# Seating Audit

## Packages Reviewed (bullets)
- `packages/seating` (`aiarmada/seating`) — seat maps/sections/seats, holds with TTL, allocations, allocator + layout contracts, Livewire seat-map picker
- `packages/filament-seating` (`aiarmada/filament-seating`) — Filament v5 admin: seat-map resource, seat-map editor + occupancy pages, overview widget

## Overall Assessment (quality, health, risks, refactor size)
- Quality: highest in the set for size. 5 models, 5 migrations, 5 actions, 2 services + null, 1 Livewire component. All models `HasOwner` (`seating.owner`), `lockForUpdate()` + transaction in the allocator, `CarbonImmutable`, UUID PKs, `getTable()` from config, `json_column_type` defined. Clean contract split (`SeatAllocatorInterface`, `SeatLayoutInterface`).
- Health: good. Two real defects: (1) `DefaultSeatAllocator::allocate()` issues one `SeatHold::create()` per seat inside a loop (N writes + N `pickSeat` queries, each re-running `availableSeatsQuery` with `whereNotIn($already)`); (2) `SeatLayoutRenderer` + `Livewire/SeatMap.php` + Filament `SeatMapEditor`/`SeatMapOccupancy` blades suggest three rendering paths for one seat map. Zero tests.
- Risks: hold-expiry races (TTL checked in query `expires_at > now` + `ReleaseExpiredHoldsCommand` batch) are handled reasonably, but per-seat `lockForUpdate()` + `first()` in a loop serializes poorly under concurrent flash sales; GA mode returns empty collection silently (callers may misread as failure vs no-op).
- Refactor size: XS–S (0.5–1.5 days). Allocator batching + render-path consolidation + tests.

## Migration Impact
**Migration Required: NO**
- Tables/columns: no changes. 5 migrations (`seat_maps`, `seat_sections`, `seats`, `seat_holds`, `seat_allocations`) all use `uuid('id')->primary()`.
- Indexes/constraints: no FK constraints/cascades (verified) — compliant. Recommend verifying composite indexes `(seat_section_id, status, row_number, column_number)` and `(seat_id, expires_at)` on holds, but no DDL in this audit.
- Data migration: none. Allocator batching is write-pattern-only.

## Package Responsibilities
- Core owns: seat-map model graph (`SeatMap` → `SeatSection` → `Seat`; `SeatHold` TTL holds; `SeatAllocation` confirmed assignments), allocation (`Services/DefaultSeatAllocator.php`, `NullSeatAllocator.php`, `Actions/EnsureSeatHoldAction.php`, `EnsureSectionAllocationAction.php`, `ConvertHoldsToAllocationsAction.php`, `ReleaseAllocationsAction.php`, `ResolveSeatMapForHostAction.php`), layout rendering (`Services/SeatLayoutRenderer.php`, `Data/SeatMapLayout.php`, `Data/AllocationResult.php`), interactive picker (`Livewire/SeatMap.php` + `resources/views/livewire/seat-map.blade.php`), expiry (`Console/Commands/ReleaseExpiredHoldsCommand.php` with `OwnerBatchRunner` + explicit-global context — correct).
- Filament adapter owns: `SeatMapResource`, `SeatMapEditor` + `SeatMapOccupancy` pages (+ blades), `SeatMapOverview` widget.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)
1. Severity: Medium. Location: `packages/seating/src/Services/DefaultSeatAllocator.php:44-69` (per-seat `pickSeat()` → `availableSeatsQuery()->first()` + `SeatHold::create()` in loop) with `availableSeatsQuery():93-105` rebuilding the query per seat (`whereNotIn('id', $already)` grows per iteration). Problem: O(N) queries + O(N) inserts for N seats; `whereNotIn` list grows linearly; row locks held for the whole loop duration inside one transaction. Why It Matters: group bookings (4–10 seats) do 8–20 queries while holding `lockForUpdate` rows — contention hotspot during on-sales. Recommended Fix: single query with `->limit($quantity)` to fetch N available seats at once (same ordering + `lockForUpdate`), then `SeatHold::insert()` the batch (one write), then build `AllocationResult` collection. Keep the transaction + `lockForUpdate`. Fall back to per-seat only if category-preference spreading requires it (even then, fetch per-category batches, not per-seat). Breaking Change: NO (same return type/ordering). Affected Packages: `events` (`EnsureCartSeatHoldAction`, `AllocateEventSeatsOnPassIssued`), `ticketing` (seating options). Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `packages/seating/src/Services/SeatLayoutRenderer.php` vs `packages/seating/src/Livewire/SeatMap.php` + `resources/views/livewire/seat-map.blade.php` vs `packages/filament-seating/src/Pages/SeatMapEditor.php` + `SeatMapOccupancy.php` + blades. Problem: re-check 2026-09-07 — `Livewire/SeatMap.php:108` already consumes the canonical renderer (`app(SeatLayoutRenderer::class)->describe($map)`), so the duplication risk is scoped to the two Filament pages (verify they consume `SeatMapLayout` rather than rebuilding coordinates). Why It Matters: section/row/column rendering drift between picker and admin. Recommended Fix: make `SeatLayoutRenderer` (returning `SeatMapLayout`) the single shape builder; Livewire + both Filament pages consume it (Filament pages may wrap, not rebuild). Delete whichever blade/PHP rebuilds coordinates manually after diff. Breaking Change: NO. Affected Packages: `filament-seating`. Required Dependent Changes: page/component rewiring. Migration Required: NO.
3. Severity: Low. Location: `packages/seating/src/Services/DefaultSeatAllocator.php:34-36` (`GeneralAdmission` returns empty `Collection`). Problem: silent no-op success — callers can't distinguish "GA needs no seats" from "allocation produced nothing". Why It Matters: upstream (`EnsureSectionAllocationAction`, events' `EnsureCartSeatHoldAction`) may treat empty as failure and retry or abort checkout. Recommended Fix: return a sentinel (e.g. throw-nothing but document) — better: keepGA early-return but add `AllocationResult::generalAdmission()` marker or boolean `requiresAllocation(SeatingMode)` helper on the enum; update callers to branch explicitly. Breaking Change: NO (additive helper). Affected Packages: `events`, `ticketing`. Required Dependent Changes: branch on helper. Migration Required: NO.

## Code Quality Findings (same finding format)
1. Severity: Low. Location: `packages/seating/src/Enums/SeatingMode.php` vs ticketing `seating_mode` strings (`open/assigned/unassigned` hardcoded in `filament-ticketing/.../TicketTypeResource.php:96-101`) vs events `InconsistentSeatingModeException`. Problem: seating-mode vocabulary defined in seating enum but retyped as raw strings in ticketing Filament + events exception paths. Why It Matters: typo drift (`assigned` vs `reserved_seating` vs `open`). Recommended Fix: ticketing Filament + events reference `SeatingMode` enum values (seating is already a required dep of ticketing; events requires seating too — no new dependency). Breaking Change: NO. Affected Packages: `ticketing`, `filament-ticketing`, `events`. Required Dependent Changes: option lists via `SeatingMode::options()`-style. Migration Required: NO.

## Laravel-Specific Findings
- PHP 8.4, strict types, UUID PKs, `HasUuids`, `getTable()` from config: PASS. `json_column_type` (`seating.php:7`): PASS. `CarbonImmutable`: PASS. No FK constraints/cascades, no soft deletes: PASS.
- Owner scoping: all 5 models `HasOwner` + `HasOwnerScopeConfig` (`seating.owner` — conventional key, exemplary). `ReleaseExpiredHoldsCommand` uses `OwnerBatchRunner` + explicit global — exemplary batch pattern other packages should copy.
- Transactions + `lockForUpdate()`: correct instinct, needs batching (A1). `DB::transaction` closure style: PASS.
- Livewire v4 (`livewire/livewire: ^4.0` required): `Livewire/SeatMap.php` — verify request-scoped state only (no static leakage per Octane rule).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)
- Thin-adapter: PASS. One resource + two pages + one widget; `SeatMapResource::getEloquentQuery():42-44` correctly applies `OwnerUiScope::apply(..., includeGlobal: false)`.
- Domain leak: none found. Editor/occupancy pages are visualization + delegation surfaces (verify mutations delegate to `EnsureSeatHoldAction`/`ReleaseAllocationsAction` rather than writing models directly).
- Dependency direction: CORRECT (requires `aiarmada/seating`). PASS. Navigation: PASS (`getNavigationGroup` from nested config on resource + both pages).
- Gap (Low): no allocation-management actions in admin (release/reassign) if ops need them — add guarded actions delegating to core when required, not before.

## Database Findings
- 5 focused migrations, UUID PKs, no constraints. Model-per-table mapping is 1:1 with no join bloat. PASS.
- Concurrency correctness rests on `lockForUpdate` + hold-expiry predicates — verify the `(seat_id, expires_at)` and section-ordering indexes exist in the migrations. Stale-hold cleanup (`ReleaseExpiredHoldsCommand`, `StaleSeatHoldException`) shows expiry was designed, not bolted on. PASS.

## Model / Domain Findings
- `SeatHold` (TTL, `held_by` morph, `reference`) vs `SeatAllocation` (confirmed) is a clean two-phase commit model; `ConvertHoldsToAllocationsAction` is the commit step. `EnsureSectionAllocationAction` covers GA/count-based sections. `ResolveSeatMapForHostAction` gives polymorphic host scoping (event/occurrence/session). Coherent minimal domain — do not expand (no seat pricing, no tier logic here; those belong to ticketing).
- `Seat.status` (`available` string checked in query) — confirm enum/constant instead of raw string in `availableSeatsQuery():98` (minor consistency fix with A-code-quality-1).

## Security Findings
- Severity: Low. Location: `packages/seating/src/Livewire/SeatMap.php` + `packages/seating/src/Actions/EnsureSeatHoldAction.php`. Problem: hold creation takes `held_by` morph + seat IDs from the client. Why It Matters: forged seat/map IDs or client-controlled TTL could over-hold. Recommended Fix: confirm server-side: seat belongs to the visible map, map belongs to current owner scope, quantity caps enforced, TTL not client-controlled. No defect found at depth; test requirement. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Performance Findings
1. Severity: Medium. Location: allocator loop (A1) — batch it; expected 8–20 queries → 2–3 per allocation. Severity/Problem/Fix/Breaking/Affected/Migration: see A1 (same change). Breaking Change: NO. Affected Packages: `events`, `ticketing` (via A1). Required Dependent Changes: none. Migration Required: NO.
2. Severity: Low. Location: `SeatLayoutRenderer` + occupancy page on large maps (thousands of seats) — paginate/virtualize or aggregate by section with counts; avoid hydrating full `Seat` models for read-only maps (select columns). Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Testing Findings
- Severity: High. Zero tests for a concurrency-critical allocator. Minimum Pest suite: allocate-N happy path, insufficient-seats exception (partial rollback proven — nothing persisted), category preferences + fallback, GA no-op contract (after A3 helper), hold expiry (`ReleaseExpiredHoldsCommand` releases only expired), double-booking race (two concurrent allocates never share a seat — DB-transaction test), cross-tenant isolation for all 5 models. Breaking Change: NO. Affected Packages: none. Required Dependent Changes: none. Migration Required: NO.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)
| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| `ticketing` | seat models, `TicketTypeSeatingOption`, release listeners | Medium — pass lifecycle drives seat release | No change; gains faster batched allocation |
| `events` | `SeatAllocatorInterface`, scope-seating actions | Medium — checkout seat holds | Adopt batched allocator transparently; use `SeatingMode` enum |
| `filament-seating` | renderer, models | Low — render-path consolidation | Consume `SeatLayoutRenderer`/`SeatMapLayout` |
| `filament-ticketing` | `seating_mode` strings | Low — enum alignment | Use `SeatingMode` values |

## Recommended Refactor Plan (ordered steps)
1. Batch the allocator (single limited select + bulk hold insert).
2. Add `SeatingMode::requiresAllocation()` (or equivalent) + update GA callers.
3. Consolidate render paths on `SeatLayoutRenderer`/`SeatMapLayout`.
4. Align `seating_mode` strings to `SeatingMode` enum across ticketing/events.
5. Verify hot-path indexes.
6. Write Pest suite incl. race test.

## Files Likely to Change
- `packages/seating/src/Services/DefaultSeatAllocator.php`, `src/Enums/SeatingMode.php`
- `packages/seating/src/Services/SeatLayoutRenderer.php`, `src/Livewire/SeatMap.php`
- `packages/filament-seating/src/Pages/SeatMapEditor.php`, `SeatMapOccupancy.php`
- `packages/filament-ticketing/src/Resources/TicketTypeResource.php` (enum options), `packages/events/src/*` seating-mode references

## Files / Code That Should Be Removed (explicit list, no legacy preservation)
- Whichever render implementation duplicates `SeatLayoutRenderer` output (Livewire-inline or Filament-page-inline layout math — confirm by diff during refactor; keep `SeatLayoutRenderer` as canonical, delete the inline rebuild)
- Raw-string `seating_mode` option arrays in `filament-ticketing` (replaced by enum; same removal as ticketing audit)

## Final Recommended Architecture
- `seating` stays the smallest package in the family: polymorphic seat-map graph, batched transactional allocator behind `SeatAllocatorInterface`, single layout renderer, TTL holds with batched expiry. No pricing, no ticket logic, no notification logic — those stay in ticketing/events/communications.
