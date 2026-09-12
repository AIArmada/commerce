# Events Audit — DONE (2026-09-09)

## Verdict

`events` and `filament-events` have passed the implementation pass. Every
rated finding is implemented, falsified with evidence, or recorded below as
an explicit bounded deferral. No migration was required at audit time; the
2026-09-12 venue shim retirement added the guarded legacy-column drop
migration (see item 3 below). The existing
`Addressable` table-prefix correction, the intentional separation between
attendance intent and the social graph, and the completed checkout,
addressing, and customers contracts were treated as fixed inputs.

## Migration Impact

**Migration Required: NO.** The ownership conversion is query- and model-layer
only; fork removal, notification bridging, helper removal, and trait cleanup
do not change tables or columns. No event migration was added, and the
database-rule scan found no new foreign-key constraints or cascades.

## What was done

### Stream A — ownership migration

**VERDICT: IMPLEMENTED with documented boundary exceptions.**

- Re-verified the baseline at 7 direct `HasOwner` models out of 64.
- Migrated the remaining owner-visible event children to the
  `ScopesByEventOwner` relation-via-owner seam. The seam applies an
  `event_owner` global scope, validates the current `OwnerContext`, guards
  writes, and supports nested and polymorphic event parents
  (`packages/events/src/Models/Concerns/ScopesByEventOwner.php:19-30,72-96,144-183,185-234`).
- Direct owner-column models continue to use `HasOwner` and
  `HasOwnerScopeConfig`; for example, `Event` keeps the configured owner
  contract (`packages/events/src/Models/Event.php:105-115`).
- Deleted the three superseded global-scope implementations only after the
  owner-parity test was green. `EventSubmissionOwnerScope` remains registered
  because submissions have distinct event-or-target semantics
  (`packages/events/src/EventsServiceProvider.php:256-260`).
- Kept `EventWriteGuard`: it has an intentional owner-disabled fallback and
  is used across the event write surface; replacing it with
  `OwnerWriteGuard` would change standalone behavior without adding proof
  (`packages/events/src/Support/EventWriteGuard.php:12-21`).
- Updated the ticketing guard to recognize direct `HasOwner` models and the
  relation-via-event capability (`packages/ticketing/src/Support/TicketingOwnerGuard.php:134-141`).
- Updated indexing to remove the new named owner scope only for the deliberate
  unscoped document rebuild (`packages/events/src/Services/EventSearchDocumentBuilder.php:187-194`).
- Filament resource queries remain owner-safe and eager-load their operational
  relations, for example events (`packages/filament-events/src/Resources/EventResource.php:60-70`),
  registrations (`packages/filament-events/src/Resources/EventRegistrationResource.php:52-60`),
  and attendance (`packages/filament-events/src/Resources/EventAttendanceResource.php:46-54`).

Files changed: event model ownership declarations and relation overrides,
`ScopesByEventOwner`, provider registration, search-document query handling,
and the ticketing/addressing guard dependencies. No seating files were edited.

Tests and exact pass output:

```text
./vendor/bin/pest --parallel tests/src/Events/CrossTenantIsolationTest.php
5 passed (34 assertions)
./vendor/bin/pest --parallel tests/src/Events
244 passed (1105 assertions)
./vendor/bin/pest --parallel tests/src/FilamentEvents
18 passed (181 assertions)
./vendor/bin/phpstan analyse packages/events/src --level=6
[OK] No errors
```

The area suites were justified before deletion because scope-class removal is
a cross-package behavioral break and must prove parity across the full event
surface, not only the focused isolation test.

### Stream B — forks, notifications, traits, venue, and hygiene

**VERDICT: IMPLEMENTED with explicit non-code deferrals.**

- Removed the four event-side ticketing forks. Ticketing is now canonical:
  `TicketTypeData::fromTicketType()` carries the optional inventory quota and
  the complete read shape (`packages/ticketing/src/Data/TicketTypeData.php:11-60`),
  while `PassData::fromPass()` carries pass read shaping
  (`packages/ticketing/src/Data/PassData.php:11-58`). Component discovery is
  canonical (`packages/ticketing/src/Actions/ExpandTicketTypeComponentsAction.php:10-36`);
  event registration projection remains a thin event-owned action
  (`packages/events/src/Actions/CreateEventComponentRegistrationsAction.php:15-27,67-133`).
- Rewired event data consumers to ticketing DTOs and removed the old event
  DTO/action files. Repository verification found zero event-side DTO/action
  namespace references.
- Added the phased communications bridge while retaining event content
  resolution and all event notification tables. Delivery sends through the
  existing communications manager with event/batch context and an event
  reference (`packages/events/src/Jobs/DispatchEventNotificationDelivery.php:111-211`);
  the mail notification is only the content adapter
  (`packages/events/src/Notifications/EventChangeNoticeNotification.php:10-29`).
- Verified natural-key idempotency for order registration creation: the
  transaction locks order lines and skips already-created registrations
  (`packages/events/src/Actions/CreateRegistrationsFromOrderAction.php:76-90,118-122,361-401`).
  No checkout or order package code was edited. The canonical cross-package
  sequence document remains an explicit documentation deferral because that
  surface was outside this stream’s write boundary.
- Kept the six high-use traits: `HasEvents`, `HasEventRegistrations`,
  `HasEventAttendances`, `HasEventLocations`, `HasEventMedia`, and
  `RecordsEventChanges`. Deleted the other 15 traits only after a repo-wide
  use-count grep proved zero consumers: `AcceptsEventSubmissions`,
  `ApprovesEventSubmissions`, `BelongsToEventSeries`, `HasEventAddress`,
  `HasEventAudience`, `HasEventClassifications`, `HasEventEligibilityRules`,
  `HasEventLanguages`, `HasEventLifecycleActions`, `HasEventLinks`,
  `HasEventParticipants`, `HasEventResponses`, `PublishesEventUpdates`,
  `ReferencedByEvents`, and `UsedAsEventMaterial`. Three active organizer
  traits remain because they are used by the owner-bearing `EventOrganizer`
  (`packages/events/src/Models/EventOrganizer.php:43-56`).
- Deleted the unused global helper file and its Composer file autoload entry;
  caller verification found no remaining global-helper calls
  (`packages/events/composer.json:1-50`).
- Consolidated content synchronization around
  `EventContentSynchronizer`, retained the thin
  `SynchronizeEventContent` wrapper, and deleted the uncalled backfill action
  (`packages/events/src/Services/EventContentSynchronizer.php:11-31`,
  `packages/events/src/Actions/SynchronizeEventContent.php:8-24`).
- Verified closed-by-default registration eligibility and policy registration:
  `DefaultEventRegistrationEligibility` rejects closed occurrences
  (`packages/events/src/Resolvers/DefaultEventRegistrationEligibility.php:18-29`),
  and the core provider registers `EventPolicy`
  (`packages/events/src/EventsServiceProvider.php:140-145`).
- Verified queued search indexing, including an enabled-queue assertion
  (`packages/events/src/Jobs/BuildEventSearchDocumentJob.php:23-95`,
  `tests/src/Events/EventSearchIndexingObserversTest.php:84-95`). Filament
  resource queries use native pagination and the relevant eager loads. The
  collection-shaped `EventQueryService::findByOwner()` remains unbounded by
  design and has no in-repo caller (`packages/events/src/Services/EventQueryService.php:44-49`);
  pagination for that public collection API is an explicit follow-up, not a
  silent API change.
- Performed a venue/address column-level review. Venue scheduling and space
  capacity remain event-owned; address and geocode access delegates through
  `Addressable`, whose resolver-backed pivot behavior is already in place
  (`packages/events/src/Models/Concerns/Addressable.php:24-38,50-64`). No blind
  column migration was made. Full `HasAddresses` adoption is deferred for
  `Venue`, `VenueSpace`, `VenueSpaceType`, `VenueFacility`, `EventFacility`,
  `FacilityType`, and `EventLocation`, because none of those models already
  carries `HasOwner`, as required by the task boundary.

Files changed: canonical ticketing DTO/action seams, event data/action imports,
notification job and content adapter, event traits/helpers, venue guard
delegation, search verification, and event tests. Communications, checkout,
orders, seating, addressing models, and the existing address-prefix fix were
not otherwise changed.

Tests and exact pass output:

```text
./vendor/bin/pest --parallel tests/src/Events/EventSessionTicketingActionsTest.php
5 passed (17 assertions)
./vendor/bin/pest --parallel tests/src/Events/EventDataConversionTest.php
4 passed (10 assertions)
./vendor/bin/pest --parallel tests/src/Events/EventNotificationDispatchTest.php
5 passed (34 assertions)
./vendor/bin/pest --parallel tests/src/Events/CreateRegistrationsFromOrderActionTest.php
9 passed (38 assertions)
./vendor/bin/pest --parallel tests/src/Events/EventSearchIndexingObserversTest.php
11 passed (25 assertions)
./vendor/bin/pest --parallel tests/src/Events/RegisterForFreeActionTest.php
11 passed (20 assertions)
./vendor/bin/pest --parallel tests/src/Events/VenueAddressingIntegrationTest.php
4 passed (14 assertions)
./vendor/bin/pest --parallel tests/src/Ticketing/Unit/PassDataTest.php
3 passed (14 assertions)
./vendor/bin/pest --parallel tests/src/Events/AssignmentRequestActionsTest.php
3 passed (22 assertions)
./vendor/bin/phpstan analyse packages/ticketing/src/Actions/ExpandTicketTypeComponentsAction.php packages/ticketing/src/Data/PassData.php packages/ticketing/src/Data/TicketTypeData.php packages/ticketing/src/Support/TicketingOwnerGuard.php packages/addressing/src/Support/AddressOwnerGuard.php --level=6
[OK] No errors
```

The full Events and FilamentEvents suites were run because both fork deletion
and ownership-scope deletion can fail through indirect imports, factories,
observers, relation managers, or panel resources.

## Rated finding outcomes

### Architecture findings

1. **Ticketing forks — IMPLEMENTED.** Canonical constructors and component
   expansion are evidenced above; event fork files were deleted, imports were
   rewired, and the repository contains zero event-side DTO/action references.
   No migration was required.
2. **Ownership framework — IMPLEMENTED with bounded exceptions.** The 64-model
   inventory is now 7 direct owner models, 46 relation-via-event models, and
   11 intentionally unscoped catalog/pivot/submission models. Parity is proven
   by `CrossTenantIsolationTest` and the 244-test Events suite. The live
   registration/ticket scope value objects and submission-specific boundary
   are intentionally retained; see Audit deviations.
3. **Venue/address overlap — CLOSED/IMPLEMENTED 2026-09-12.** The legacy
   read trait is deleted; `Venue` and `EventLocation` use only `HasAddresses`
   and all owned consumers read canonical `primaryAddress()` fields. The
   guarded drop migration removes the former venue/location address columns
   with no backfill; zero-reference and rerun proofs are in
   `tests/src/Events/VenueAddressShimRemovalTest.php:10-104`. The remaining
   non-owner venue/facility models are documented rather than given unsafe
   duplicate owner adoption.
4. **Notifications — IMPLEMENTED as the requested phased bridge.** Content
   stays in events; delivery uses communications context plus an event
   reference; event batch/delivery models remain. Table retirement and its
   migration are explicitly out of scope. The communications normalizer is a
   logged dependency, not a hidden cross-package edit.
5. **Order listeners — IMPLEMENTED for idempotency; documentation DEFERRED.**
   Transactional natural-key dedupe and replay coverage are present. The
   cross-package sequence document is not edited because `docs` was read-only
   to this stream; no checkout/order changes were made.
6. **Trait surface — IMPLEMENTED.** Six high-use traits remain; 15 zero-use
   traits were removed after grep verification; the three live organizer traits
   remain for the `EventOrganizer` seam.

### Code-quality findings

1. **Global helpers — IMPLEMENTED.** The helper file and Composer file-autoload
   entry were removed after a zero-caller search; existing config helpers are
   used by the remaining migrations.
2. **Contract/Null breadth — DROPPED AS UNPROVEN.** No bulk contract deletion
   was justified. The candidate single-implementation contracts were kept as
   extension seams, and all Null adapters were preserved for standalone
   installation behavior.
3. **Content-sync overlap — IMPLEMENTED.** The service is canonical, the
   remaining action is a thin wrapper, and the uncalled backfill action was
   deleted after caller verification.

### Security findings

1. **Eligibility — IMPLEMENTED/VERIFIED.** Closed occurrence state is rejected
   server-side and the focused eligibility suite passes 11 tests (20
   assertions); unknown or unavailable paths remain deny-by-default.
2. **Filament policy registration — IMPLEMENTED/VERIFIED.** The core provider
   registers the event policy before the Filament resources are used; resource
   owner isolation and eager-load assertions pass in the FilamentEvents area
   suite.

### Performance findings

1. **Search queueing — IMPLEMENTED/VERIFIED.** The search job is queued and
   owner-context aware when queue indexing is enabled; the observer test
   asserts the dispatched job. Full-text/index-engine replacement remains a
   future scale decision and no schema change was made.
2. **Listing bounds/eager loads — VERIFIED with one explicit deferral.** The
   Filament resource surface is paginated by native ListRecords behavior and
   operational resources eager-load required relations. The standalone
   collection method `findByOwner()` remains unbounded, with no in-repo caller;
   adding pagination would be an API decision and is recorded as follow-up.

### Testing finding

**IMPLEMENTED for the audited risk surface.** Focused tests cover owner parity,
DTO conversion, bundle/component behavior, notification bridging, replay
idempotency, eligibility, search queueing, venue addressing, and panel/resource
surfaces. The complete owned area suites pass as recorded above; no full
monorepo suite was run.

## Residual notes

- Event notification table retirement remains a separate migration project;
  this pass deliberately did not delete models or tables.
- Full `HasAddresses` adoption for the remaining non-owner venue/facility
  models (`Venue` and `EventLocation` adopted 2026-09-12) remains deferred
  until their owner contract is established.
- The canonical order→registration→pass sequence document and pagination API
  decision remain documentation/API follow-ups.
- Communications owns the event-reference normalizer
  (`Support/EventReferenceNormalizer`); the events bridge consumes it with
  the existing communications context/reference APIs.
- Existing event-specific seating orchestration and the intentional attendance
  versus social-graph separation remain unchanged.

## Audit deviations

- The original “five scope files” deletion instruction was narrowed by code
  evidence: `EventRegistrationScope` and `EventTicketScope` are live typed
  value/domain objects with broad action/test usage, not global owner scopes.
  Deleting them would remove registration and ticket-target semantics, so they
  were retained. Three obsolete global-scope implementations were deleted
  after parity passed.
- The event DTOs were not byte-identical copies: they had divergent read and
  write shapes. The duplication finding was still resolved by moving the
  useful named constructors into canonical ticketing and deleting the event
  forks; no compatibility aliases were added.
- `EventWriteGuard` was retained because its owner-disabled behavior differs
  from `OwnerWriteGuard` and its event write call sites are widespread.
- Venue `HasAddresses` is now the only address path on `Venue` and
  `EventLocation` (legacy trait deleted 2026-09-12, columns dropped via the
  guarded migration). The remaining named venue/facility models that carry
  neither `HasOwner` nor `HasAddresses` keep documented behavior rather than
  unsafe duplicate adoption.
- The cross-package canonical sequence doc was not edited because `docs` was a
  read-only surface for Stream B. This is an explicit deferral, not an
  unverified claim.
- The communications normalizer was not edited because `communications` was
  read-only. The event bridge uses the installed `CommunicationContextData`
  and `AttachCommunicationReferenceAction`; the normalizer dependency is
  recorded above.
- Seating’s event-scope actions remain dependent on event/occurrence/session
  visibility; no seating file was edited, and the new relation boundary keeps
  that dependency at the event seam.
- Integration required narrow edits outside the two primary sets: canonical
  ticketing `PassData` and component expansion were needed to remove the
  divergent event forks; `TicketingOwnerGuard` was the named owner-sniff
  dependency; and `packages/addressing/src/Support/AddressOwnerGuard.php`
  first dropped its dead coupling to the retired event ownership classes,
  then (review holding finding, now closed) restored full owner-tuple
  enforcement via `belongsToOwner()` with explicit-global handling and
  fail-closed behavior — cross-tenant attach rejected, same-tenant
  allowed, covered by the venue isolation test.
  These are listed rather than concealed in the status handoff.
- Package-context, ticketing-audit, package-index, and stale evidence entries
  were refreshed so the repository does not retain references to deleted
  ownership implementations. These are documentation/evidence hygiene only.
- No seating, checkout, orders, communications, addressing models, or
  customer contract changes were made. The previously completed
  `Addressable` prefix fix and engagement-boundary decision were not redone.
