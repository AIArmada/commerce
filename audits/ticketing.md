# Ticketing Audit — DONE (2026-09-11)

## Verdict

The ticketing pair (ticketing + filament-ticketing) is cleared. Every rated
finding is implemented, pre-resolved, falsified with source evidence, or
recorded as an explicit deferral. The events package remained read-only.

## What was done

- **Registry placement (High):** TicketableTypeRegistry now belongs to core
  at packages/ticketing/src/Support/TicketableTypeRegistry.php:10-50 and
  reads ticketing.ticketable_types at
  packages/ticketing/config/ticketing.php:25-26. The Filament copy,
  adapter binding, and old config keys were removed; the adapter imports the
  core class at packages/filament-ticketing/src/Resources/TicketTypeResource.php:10-15.
  A repo scan found no old registry imports in events, orders, cart, or
  checkout.
- **Batch issuance (Medium):** DefaultPassIssuer now generates a batch,
  probes pass_no with one whereIn query, retries only collisions, performs
  one insert, and registers one PassIssued event per pass after commit
  (packages/ticketing/src/Services/DefaultPassIssuer.php:23-67,
  :73-107, :155-215). Unrelated query failures are rethrown.
- **Canonical event-facing DTOs (Low):** fromTicketType and fromPass were
  already present with the quota logic when this stream verified the events
  contract (packages/ticketing/src/Data/TicketTypeData.php:34-60 and
  packages/ticketing/src/Data/PassData.php:31-59). This was recorded as
  pre-resolved; no events fork or stable events contract was changed.
- **Owner/config correctness:** the owner key is consistently
  ticketing.owner in config and all seven owned models. TicketingOwnerGuard
  uses the HasOwner contract directly
  (packages/ticketing/src/Support/TicketingOwnerGuard.php:23-36,
  :134-141). Filament ticket type, holder, and transfer queries apply
  OwnerUiScope (for example packages/filament-ticketing/src/Resources/TicketTypeResource.php:50-64).
- **Filament option and empty-registry findings (Low):**
  TicketTypeResource uses TicketAccessType, TicketTypeStatus, and
  SeatingMode enum options at
  packages/filament-ticketing/src/Resources/TicketTypeResource.php:92-122.
  An empty registry now leaves the owner-scoped query visible instead of
  replacing it with a blanket false predicate.
- **Security and transfer verification:** the global pass_no uniqueness
  probe is deliberately narrow and read-only; PassTransferPolicy and
  DefaultPassTransferService enforce holder/window rules server-side. The
  passes migration retains the unique pass_no index at
  packages/ticketing/database/migrations/2000_01_01_000004_create_passes_table.php:20.
- **Testing finding (High):** issuance quantity, collision retry, rollback,
  lifecycle, transfer, owner isolation, and registry/resource behavior now
  have targeted coverage.

## Residual notes

- No migration is required for the ticketing changes.
- The canonical DTO constructors and the HasOwner-based guard were already
  aligned with the stable events contracts, so they were verified rather than
  recreated.

## Audit deviations

- tests/src/Events/CrossTenantIsolationTest.php:134 is the only repo match
  for the old ticketing.features.owner key. It is an events test in a
  read-only package and is logged as a micro follow-up; it was not touched.
- The owned release action handles a complete allocation set, but still
  releases each model and emits each model event at
  packages/seating/src/Actions/ReleaseAllocationsAction.php:22-35.
  A multi-allocation regression now proves the path releases the whole set.
  The broader event-registration revoke loop at
  packages/events/src/Actions/RevokePassesForRegistrationAction.php:17-26
  remains an explicit read-only dependency and is not changed here.

## Verification

- Ticketing Area: 43 passed, 79 assertions.
- FilamentTicketing Area: 8 passed, 20 assertions.
- IssuePassesAction focused regression: 4 passed, 8 assertions.
- All commands used Pest --parallel; no full-suite run was performed.

If either residual dependency becomes actionable inside its owning package,
re-open this audit as a new finding.
