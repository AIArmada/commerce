# Seating Audit — DONE (2026-09-11)

## Verdict

The seating pair (seating + filament-seating) is cleared. Every rated finding
is implemented, falsified with source evidence, or recorded as an explicit
deferral. Events-side code remained read-only.

## What was done

- **Set-based allocation (Medium):** DefaultSeatAllocator keeps one
  transaction, fetches up to the requested quantity with limit and
  lockForUpdate, and inserts SeatHold rows as one batch
  (packages/seating/src/Services/DefaultSeatAllocator.php:36-64,
  :71-93, :100-146). Category preferences use preferred and fallback
  batches rather than a per-seat query loop.
- EnsureSeatHoldAction received the same transaction, set-based selection,
  batch insert, and rollback behavior
  (packages/seating/src/Actions/EnsureSeatHoldAction.php:34-50,
  :57-146).
- **GA sentinel (Low):** SeatingMode now distinguishes map/capacity
  requirements from actual seat allocation through requiresSeatAllocation()
  (packages/seating/src/Enums/SeatingMode.php:18-26). Owned callers branch
  explicitly; GeneralAdmission and None remain intentional no-op results.
- **Renderer finding (Low):** dropped as falsified. SeatLayoutRenderer is
  the canonical shape builder at packages/seating/src/Services/SeatLayoutRenderer.php:10-64.
  Livewire consumes it at packages/seating/src/Livewire/SeatMap.php:100-108,
  and both Filament pages delegate to that Livewire component at
  packages/filament-seating/resources/views/pages/seat-map-editor.blade.php:1-2
  and seat-map-occupancy.blade.php:1-2. No duplicate coordinate rebuild was
  found to delete.
- **Enum alignment (Low):** filament-ticketing now uses SeatingMode::options()
  at packages/filament-ticketing/src/Resources/TicketTypeResource.php:95-97.
  Events already consumes the shared SeatingMode enum; no raw seating-mode
  option list remained in the owned surfaces.
- **Security verification (Low):** seat selection is constrained to the
  supplied map's sections, available seats, the current owner scope, and
  server-configured TTL; quantity failure occurs before the hold insert.
  Owner/isolation, insufficient-seat rollback, category fallback, and hold
  behavior are covered by the Area tests.
- **Indexes and testing:** the existing seat and hold indexes were verified
  at packages/seating/database/migrations/2000_01_01_000003_create_seats_table.php:23-30
  and 2000_01_01_000004_create_seat_holds_table.php:19-24. The former zero-test
  finding is closed with allocator, hold, mode, expiry, isolation, and
  release-path coverage.

## Residual notes

- No migration is required for seating.
- Allocation-management actions in the Filament admin and large-map
  virtualization/column-select optimization remain product/performance
  follow-ups, not correctness changes required by this pass.
- Seat.status remains a string-backed persisted lifecycle field; converting
  that vocabulary to a new enum is an explicit minor consistency deferral,
  with the existing available() scope and behavior tests retained.

## Audit deviations

- Events keeps intentional capacity semantics: its
  EnsureCartSeatHoldAction, AllocateEventSeatsOnPassIssued, and
  EnsureEventScopeSeatingAction use requiresAllocation() at
  packages/events/src/Actions/EnsureCartSeatHoldAction.php:34-42,
  packages/events/src/Actions/AllocateEventSeatsOnPassIssued.php:43-53,
  and packages/events/src/Actions/EnsureEventScopeSeatingAction.php:20-29.
  GeneralAdmission still needs a section/map for capacity validation even
  though it does not reserve individual seats. These files are read-only.
- The owned ReleaseAllocationsAction still emits one release event per
  allocation at packages/seating/src/Actions/ReleaseAllocationsAction.php:22-35;
  the explicit multi-allocation test proves the path is complete. The
  registration-wide revoke loop remains in read-only events code at
  packages/events/src/Actions/RevokePassesForRegistrationAction.php:17-26
  and is deferred to its owner.

## Verification

- Seating Area: 62 passed, 116 assertions.
- FilamentSeating Area: 8 passed, 9 assertions.
- SeatAllocator focused regression: 9 passed, 17 assertions.
- EnsureSeatHoldAction focused regression: 5 passed, 9 assertions.
- SeatingMode focused regression: 6 passed, 19 assertions.
- ReleaseAllocationsAction bulk-path regression: 4 passed, 8 assertions.
- All commands used Pest --parallel; no full-suite run was performed.

If the deferred admin/performance or events-owned work becomes required,
re-open it as a new finding.
