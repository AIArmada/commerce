E2E review: seating, orders, filament-events, filament-inventory. Repo-rule compliance is good overall (uuid PKs, no FK constraints — foreignUuid without constrained(), int minor-unit money, no SoftDeletes, navigation.group + getNavigationGroup everywhere, Actions for orchestration). Findings below; positives at end.

## SEATING

1) HIGH / bug — `packages/seating/src/Actions/EnsureSeatHoldAction.php:136-147` (+ `Services/DefaultSeatAllocator.php:148-159` same pattern). Concurrent double-hold race. Availability is decided by `SELECT … lockForUpdate` on seats + `whereDoesntHave('holds', expires_at > now)`, then holds are bulk-inserted. Under MySQL REPEATABLE READ (default) a second txn blocked on the row lock still uses its start-of-txn snapshot after unblocking, so it re-selects the same seats; unlike allocations (partial unique index on pgsql/sqlite, `2000_01_01_000005` migration), `seat_holds` has no DB guard on active holds. Evidence: `availableSeatsQuery()` + `SeatHold::query()->insert($rows)`. Recommendation: add a conditional unique guard for unconverted, unexpired holds per seat (or lock + re-check holds after acquiring seat locks with `lockForUpdate` on the holds subquery / SELECT … FOR UPDATE on existing hold rows + unique key), and document required isolation level. Confidence: med (isolation-dependent).

2) MEDIUM / performance — `packages/seating/src/Services/SeatLayoutRenderer.php:19-49`. N+1: one seats query + one `max(column_number)` query per section. Recommendation: eager-load sections with seats once, compute bounds in memory. Confidence: high.

3) MEDIUM / performance — `packages/seating/src/Livewire/SeatMap.php:101-154`. `getLayoutProperty` + `getStatusProperty` load the entire venue (all sections/seats/holds/allocations) on every Livewire round-trip; `toggleSeat` re-resolves the map each click; `$picked` is unbounded. Large venues = multi-MB Livewire payloads + full-table scans per click. Recommendation: paginate/virtualize by section, cache layout per map version, cap selection size. Confidence: high.

4) MEDIUM / bug — `packages/seating/src/Actions/ConvertHoldsToAllocationsAction.php:62-72` and `EnsureSectionAllocationAction.php:37-44`. Converted allocations never copy the source hold's/section's owner; owner comes purely from ambient `OwnerContext` via HasOwner auto-assign. Cross-owner batch conversion misattributes ownership. (Orders models copy parent owner in `creating` hooks; seating has no equivalent.) Recommendation: set owner from `$lockedHold`/parent explicitly. Confidence: med.

5) MEDIUM / bug — `packages/seating/src/Actions/EnsureSeatHoldAction.php:116-122` (same in `DefaultSeatAllocator.php:130-136`). `SeatHold::query()->insert($rows)` bypasses HasOwner `creating`/`saving` guards (explicit-global assertion, owner/context match). With unresolved context, holds are silently created global. Recommendation: assert `OwnerContext::resolve() !== null || isExplicitGlobal()` before insert (HasOwner does this for `create()`). Confidence: high.

6) LOW / bug — `packages/seating/src/Console/Commands/ReleaseExpiredHoldsCommand.php:22,41-47`. `--chunk` unvalidated: 0/negative breaks `chunkById`, huge values OOM via `$holds->each->delete()`. Recommendation: clamp to a sane range. Confidence: high.

7) LOW / performance — `SeatMap.php:41-46`, `SeatSection.php:40-46`, `Seat.php:45-51`. Cascading deletes via `->each(fn => ->delete())` = N queries, no chunking. Recommendation: chunk deletes. Confidence: high.

## ORDERS

8) HIGH / bug (financial) — `packages/orders/src/Transitions/PaymentConfirmed.php:38-98`, passthrough `Actions/RegisterOrderPayment.php:18-30`. No amount validation: a 0/negative-amount "payment" creates a Completed payment, transitions to Processing and sets `paid_at` (the `created` hook only guards `paid_total` increments, not the transition). No balance-due/overpayment check either — inconsistent with `RefundProcessed` which validates `amount > 0`. Recommendation: require `amount > 0` (and optionally `<= balance due`) before recording. Confidence: high.

9) MEDIUM / bug — `packages/orders/src/Actions/CreateOrder.php:99-114,240-257`. Caller-supplied order totals trusted verbatim (grand_total can be 0 with items); `addItem` uses `$itemData['name']` (missing key = Error, not validation) and accepts negative quantity/unit_price/discount/tax. Recommendation: validate items (name required, qty >= 1, amounts >= 0) and recompute/verify totals server-side. Confidence: high.

10) MEDIUM / bug — `packages/orders/src/Models/Order.php:398-408`. `recalculateTotals()` ignores per-item `discount_amount` (uses order-level `discount_total` only), so item discounts silently vanish from grand_total. Recommendation: subtract `sum(discount_amount)` or document that item discounts are unsupported. Confidence: high.

11) MEDIUM / security — `packages/orders/src/Policies/OrderPolicy.php:15-69`. Owner-blind: view/update/delete/cancel/refund check only `$user->can(...)`, while `Policies/Concerns/HandlesOrderRelationAuthorization.php` enforces owner checks for relations. Any holder of `view_order` can access any owner's orders wherever the policy is the enforcement point. Recommendation: mirror the relation-trait owner checks in OrderPolicy. Confidence: med (depends on call-site enforcement; Filament layer may scope separately).

12) MEDIUM / bug — `packages/orders/database/migrations/2000_11_01_000001_create_orders_table.php:57-60` + `Actions/CreateOrder.php:333-340`. Intake dedup unique key `(owner_type, owner_id, intake_source, intake_id)` contains nullable columns; on MySQL NULLs are distinct, so global-owner (or any NULL) duplicates bypass the DB constraint and concurrent same-intake creates both pass the app-level `findExistingIntake` check. Recommendation: require non-null intake pair scoping or serialize on intake key (e.g. locked sentinel row / cache lock). Confidence: med.

13) LOW / bug — `packages/orders/src/Actions/GenerateInvoice.php:64-88`. Fresh random invoice number minted on every download, never persisted — duplicate/conflicting invoice numbers, accounting-hostile (receipt correctly reuses order number). Recommendation: persist invoice number per order. Confidence: high.

14) LOW / bug — `packages/orders/src/States/OrderStatus.php:153-157` defaults new orders to Processing while the migration defaults `status` to 'created' and the lifecycle starts Created→PendingPayment. Direct `Order::create()` without status skips the payment flow. Recommendation: align default to Created. Confidence: med.

## FILAMENT-EVENTS

15) HIGH / bug — Record lifecycle actions placed in table `headerActions` (no record context), so every click fails: `Resources/EventResource.php:126-160` (publish/archive/cancel), `Resources/EventOccurrenceResource.php:103-153` (delay/postpone/cancel/complete), `Resources/EventSessionResource.php:119-172` (same four). Closures type-hint non-nullable `Event $record` etc., but header actions receive no record → TypeError. Corroborated in-repo: clone actions correctly live in `->actions([...])`, and Attendance/Venue/Registration resources use header actions only for Import/Export. Recommendation: move all record actions to `actions()`. Confidence: high (framework contract + in-repo counter-examples).

16) HIGH / security — Importers accept cross-owner foreign IDs with zero revalidation, violating the package's own guardrail: `Actions/Importer/EventSessionImporter.php:19-49` (`event_id`, `event_occurrence_id`, no rules) and `Actions/Importer/EventRegistrationImporter.php:27-68` (`event_id`, `event_occurrence_id`). An import can attach sessions/registrations to another owner's events; records then auto-assign ambient owner. Recommendation: `OwnerWriteGuard::findOrFailForOwner(Event::class, …)` in `beforeCreate`/`beforeSave` (as `CreateEventOccurrence`/`CreateEventSession` pages already do). Confidence: high.

17) MEDIUM / security — `Actions/Importer/VenueImporter.php:30-49`. `address_id` resolved via unscoped `Address::query()->findOrFail($state)` then attached to the venue — cross-context address attach. Recommendation: scope the lookup to the current owner context. Confidence: med (Address owner config not verified here).

18) MEDIUM / bug — `Resources/EventResource/Pages/CreateEvent.php:21-24` (identical in EditEvent, ListEvents, ViewEvent and all four EventTemplate pages): `boot()` calls `OwnerContext::setForRequest(null)`, forcing explicit-global for the entire request and wiping any resolver-provided owner. With `events.owner.enabled=true` (default), the event admin becomes global-only and newly created events are global instead of owned. Recommendation: remove; let the resolver supply context (or scope explicitly per query). Confidence: high on mechanism, med on production impact (depends on resolver binding).

19) MEDIUM / security+correctness — `Pages/ApprovalQueue.php:73-129`. approve/reject/assign perform direct `$record->update(...)` with no authorization check (any page viewer can approve) and bypass any domain workflow on `EventSubmission` (no status transition/audit). Recommendation: gate with policy/permission + drive approval through the events-domain workflow. Confidence: med-high.

20) LOW / bug — `Resources/EventResource.php:221`. Slug `unique(ignoreRecord: true)` is global across owners → slug collisions deny creation for other tenants. Recommendation: scope uniqueness per owner. Confidence: med.

21) LOW / performance — `Pages/CheckInConsole.php:103-110`. Leading-wildcard `LIKE %…%` on `pass_no`/`registration_no` (unindexed scan) with uninterpolated user input, so `%`/`_` act as wildcards. Recommendation: escape LIKE specials. Confidence: high.

Note (not findings): Venue, VenueSpace, EventTaxonomy, EventTerm models verified to have no HasOwner (global vocabularies/venues), so their resources' lack of `getEloquentQuery` scoping is consistent. Exporters ride the scoped table query. `EventChangeLogResource ->html(JsonDisplay::format())` is escaped (`e()` in JsonDisplay) — no XSS.

## FILAMENT-INVENTORY

22) HIGH / bug — `Actions/CycleCountAction.php:66-69,102`. `system_quantity` is a `disabled()` display field, but the submit handler reads `$data['system_quantity']` — disabled fields are not dehydrated, so this is an undefined-key error on submit. It also trusts a client-submitted system quantity for the variance note. Recommendation: `->dehydrated()` or (better) recompute system quantity server-side in the action. Confidence: med (framework behavior; verify by running the action).

23) LOW / performance — Navigation badges fire COUNT queries on every admin request: `InventoryLevelResource.php:78-86` (plus `whereRaw`), `InventoryLocationResource.php:116-121`, `InventoryAllocationResource.php:74-81`, `InventoryBatchResource.php:118-123`. Recommendation: cache briefly or drop badges. Confidence: high.

24) LOW / bug — `Widgets/ExpiringBatchesWidget.php:30-37`, `Widgets/BackordersWidget.php:30-36`, `Widgets/ReorderSuggestionsWidget.php:31-37` bake `->limit(10)` into the Filament table query, corrupting pagination totals. Recommendation: use default page size instead of limit. Confidence: med.

25) LOW / performance — `Services/InventoryStatsAggregator.php:106-122`. `Cache::remember` with 60s TTL has no stampede protection; concurrent misses all recompute. Impact is low (short TTL, cheap reports). Recommendation: `Cache::flexible()`/lock if it ever shows up in traces. Confidence: low-med.

## POSITIVES (brief)
- Owner scoping done right in most places: orders child models copy parent owner + `OwnerWriteGuard` in `creating` hooks; `AssertsOrderOwnerBoundary` on mutations; `OrderProcessingCheck` fails safe without context; filament-inventory consistently applies `InventoryOwnerScope` in resources, widgets, per-action location revalidation, and owner-suffixed cache keys; inventory domain reports/ValuationService/KPI services all scope internally (verified).
- Money integrity: paid/refunded/pending totals synced via atomic increments in model hooks; idempotent payment/refund identities backed by unique DB indexes + race handling; `RefundAllocationValidator` enforces allocation sums.
- No XSS sinks found: invoice/notification blades use escaped output; `JsonDisplay` escapes; no `{!!`, `HtmlString`, `eval`, deserialization, SSRF, or path-traversal sinks in the four packages (GenerateInvoice `save($path)` takes caller paths only).
- No Octane-unsafe mutable static state (only config-key declarations); `OwnerContext` uses request attributes + finally-restored fallback.
- Migrations well indexed (seat lookup composites, order status/customer composites, payment/refund gateway indexes); no FK violations of the no-FK rule.
- Test coverage exists for all four areas (notably orders lifecycle/refunds/intake, seating allocator/conversion/expiry, FilamentEvents importer/approval/page surfaces, FilamentInventory aggregator/scope/plugin).
