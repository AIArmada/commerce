# jnt Audit

## Packages Reviewed (bullets)

- `packages/jnt` — J&T Express Malaysia integration: orders/items/parcels/tracking-events, API client, shipping driver, cart integration, webhooks, notifications, console commands (83 `src/` files, `config/jnt.php`, 5 migrations, `routes/web.php` + `routes/webhooks.php`)
- `packages/filament-jnt` — Filament adapter: `JntOrder/JntTrackingEvent/JntWebhookLog` resources over `BaseJntResource`, 3 table actions, stats widget (27 `src/` files, `config/filament-jnt.php`)
- Root test coverage consulted: `tests/src/Jnt/` (53 files — strongest in set), `tests/src/FilamentJnt/` (11 files)

## Overall Assessment (quality, health, risks, refactor size)

jnt is a full-featured carrier integration with correct tenancy plumbing (`HasOwner`+`HasUuids`+`getTable()` on all domain models, `OwnerContext::withOwner` in `JntTrackingService` and `SendShipmentNotifications`), minor-unit money columns (`*_value_minor`), and the best test depth in this review set (53 root tests). The findings are economy and precision: (1) two parallel event taxonomies for the same occurrences (`TrackingUpdated` flat payload vs `TrackingUpdatedEvent` DTO, plus `Parcel*` vs `TrackingStatusReceived` vs `JntOrderStatusChanged` — 11 event classes, both verified live); (2) `JntShippingDriver` does float money math (`(int) round($rate * $regionMultiplier)`) bypassing `MoneyNormalizer`; (3) three cart-integration layers (`CartManagerWithJntShipping`, `JntShippingConditionProvider`, `JntShippingCalculator`) for one calculation; (4) `JntWebhookLog` maps to the shared `webhook_calls` table with a package-global `OwnerScope` — correct per-row ownership is enforced in `booted()` via cross-owner check, but the global scope on a shared table affects other packages' rows; (5) filament table actions use `->authorize(fn () => Filament::auth()?->check())` — authentication theater, not authorization (the real check, `OwnerWriteGuard::findOrFailForOwner`, happens inside — keep the inner, fix the outer). No schema migration required. Refactor size: Medium, code-only.

## Migration Impact

**Migration Required: NO**

| Table | Column/Index/Constraint | Data migration | Notes |
|---|---|---|---|
| `jnt_orders`, `jnt_order_items`, `jnt_order_parcels`, `jnt_tracking_events` | none proposed | none | uuid PKs verified; no FK constraints (rg clean) |
| `webhook_calls` (+ jnt columns migration) | none proposed | none | Shared Spatie table, `bigIncrements` PK kept intentionally; `JntWebhookLog` correctly omits `HasUuids` |
| none | none | none | Event consolidation (E1) is class-level; event names on the wire unchanged |

## Package Responsibilities

- API surface: `Http/JntClient.php` (timestamp signing, retry), `Data/*` (Order/Item/PackageInfo/Tracking/Webhook DTOs), `Builders/OrderBuilder.php`, `Rules/*` (phone, postcode, weight, dimensions, monetary), `Facades/JntExpress.php`, `Services/JntExpressService.php`.
- Persistence: `Models/{JntOrder,JntOrderItem,JntOrderParcel,JntTrackingEvent,JntWebhookLog}.php`, `Actions/Orders/{CreateOrder,CancelOrder}.php`, `Actions/Tracking/TrackParcel.php`, `Actions/Waybills/PrintWaybill.php`, `Services/JntTrackingService.php`, `Services/JntStatusMapper.php` + `Support/StatusMappingStrategyRegistry.php`.
- Carrier webhooks: `routes/webhooks.php`, `Http/Controllers/{WebhookController,AwbController}.php`, `Webhooks/{JntSpatieSignatureValidator,JntWebhookProfile,JntWebhookResponse,ProcessJntWebhook}.php`, `Services/WebhookService.php`.
- Commerce integration: `Shipping/JntShippingDriver.php`, `Cart/{CartManagerWithJntShipping,JntShippingConditionProvider,JntShippingCalculator}.php`, `Support/Integrations/CartIntegrationRegistrar.php`, `Listeners/SendShipmentNotifications.php`, `Notifications/*`, `Health/JntHealthCheck.php`, console commands.

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### E1 — Two parallel tracking-event taxonomies for the same occurrences
- Severity: High
- Location: `packages/jnt/src/Events/TrackingUpdated.php` (flat `billcode/eventType/payload`, dispatched in `Webhooks/ProcessJntWebhook.php:176,198`) vs `Events/TrackingUpdatedEvent.php` (typed `TrackingData` DTO, covered in `tests/src/Jnt/Feature/Events/TrackingUpdatedEventTest.php`); plus `ParcelDelivered/InTransit/OutForDelivery/PickedUp`, `TrackingStatusReceived`, `JntOrderStatusChanged`, `OrderCreatedEvent`, `OrderCancelledEvent`, `WaybillPrintedEvent` (11 classes; both taxonomies verified live, so neither is dead)
- Problem: Two shapes describe "tracking changed" — untyped array vs typed DTO — with overlapping siblings for each leg transition. Subscribers must listen twice or miss occurrences; new legs get added to one taxonomy only.
- Why It Matters: Event-shape drift across 11 classes is how `ProcessJntWebhook` and the tracking service permanently disagree about what "delivered" means.
- Recommended Fix: Canonicalize on the typed DTO taxonomy: `TrackingUpdatedEvent(TrackingData)` becomes the single occurrence event; derive leg-specific handling from `TrackingData`/status enum inside subscribers (no per-leg classes); keep `JntOrderStatusChanged` (order-level, distinct aggregate) and `OrderCreated/CancelledEvent`, `WaybillPrintedEvent` (distinct occurrences). Migrate the two `ProcessJntWebhook` dispatch sites + `EventsCoverageTest` + `ProcessJntWebhookTest` in the same pass, then delete `TrackingUpdated`, `Parcel*`, `TrackingStatusReceived`.
- Breaking Change: YES
- Affected Packages: jnt (internal), host apps subscribed to deleted classes, signals (`Record*` listeners if subscribed — verify via rg `Jnt\\Events` before merge)
- Required Dependent Changes: update subscribers/registrations to `TrackingUpdatedEvent`; tests updated same pass
- Migration Required: NO

### E2 — Float money math in the shipping driver bypasses the money standard
- Severity: High
- Location: `packages/jnt/src/Shipping/JntShippingDriver.php:377` (`(int) round($rate * $regionMultiplier)`), `:383-398` (region multipliers as floats from `jnt.shipping.region_multipliers`), `:338` (`round(weight/1000, 2)`)
- Problem: Rate computation multiplies a (presumably minor-unit) rate by a float multiplier and rounds — float error at the money boundary, bypassing `MoneyNormalizer`/`MoneyFormatter` which commerce-support owns and this package otherwise respects (`valueMinor` minor-unit DTO fields, `*_value_minor` columns).
- Why It Matters: Off-by-one-sen quotes that disagree with charged amounts; the exact bug class the monorepo money rule exists to kill.
- Recommended Fix: Represent multipliers as integer basis points (15000 = 1.5x) in config (`region_multipliers_bp`), compute `($rate * $bp + 5000) / 10000` in integers, keep a single `round()` only at the carrier-API weight boundary (kg Fuente externa, documented as carrier-required decimals, never money). Add a unit test locking `rate=100 × sabah 1.5 = 150`.
- Breaking Change: YES (config key shape changes; provide `jnt.shipping.region_multipliers` → `_bp` migration note in docs, update all internal readers in same pass)
- Affected Packages: cart/shipping integrations reading the multiplier config (verify via rg `region_multipliers`)
- Required Dependent Changes: config updates where overridden in host apps (documented, one key)
- Migration Required: NO

### E3 — Three cart-integration layers for one calculation
- Severity: Medium
- Location: `packages/jnt/src/Cart/CartManagerWithJntShipping.php`, `Cart/JntShippingConditionProvider.php`, `Cart/JntShippingCalculator.php`, registered via `Support/Integrations/CartIntegrationRegistrar.php`
- Problem: Manager + condition-provider + calculator triple-wrap a single "quote shipping for cart" operation. The seam between provider and calculator is not a real extension point (no second implementation exists or is plausibly coming).
- Why It Matters: Callers cannot tell which layer to use; fixes land in the wrong layer; tests mock two layers to test the third.
- Recommended Fix: Collapse to `JntShippingCalculator` (pure: cart snapshot → quotes) + registrar wiring; fold provider/manager logic in. Keep `JntShippingDriver` (carrier API) untouched.
- Breaking Change: YES
- Affected Packages: cart (integration point), host apps using `CartManagerWithJntShipping`
- Required Dependent Changes: update registrar + any `CartManagerWithJntShipping` references (rg first — believed few)
- Migration Required: NO

### E4 — `JntWebhookLog` global scope on the shared `webhook_calls` table
- Severity: Medium
- Location: `packages/jnt/src/Models/JntWebhookLog.php:45-58` (`HasOwner` + `getTable()='webhook_calls'` + `addGlobalScope('jnt_webhook_calls', where name=...)`)
- Problem: The `OwnerScope` from `HasOwner` applies to every `JntWebhookLog` query — which is every `webhook_calls` row filtered by name, so far so good — but the per-row ownership is inherited from the parent order in `creating()` (`$log->owner_type = $order->...`), and the cross-owner check does `JntOrder::query()->withoutOwnerScope()->find()` per create. Under a flood of webhooks this is an extra unscoped order lookup per log row, and any other package querying `webhook_calls` through this model inherits jnt's owner semantics.
- Why It Matters: Shared-table models with owner scopes are a cross-package blast radius: a scope misconfiguration in jnt filters another integration's webhook rows.
- Recommended Fix: Keep the model but scope explicitly per query instead of globally: remove `HasOwner`'s global scope for this model only (override `resolveOwnerScopeConfig()` with `enabled:true` but register no global scope — or simpler, keep `HasOwner` for the write guards and replace reads with `forOwner()` at the 2–3 read sites: Filament resource via `BaseJntResource::getEloquentQuery()` already applies `OwnerUiScope`, and `WebhookService` lookups). Concretely: override `bootHasOwner` behavior by setting `ownerScopeConfig` with a model-local flag the team agrees (or move the cross-owner assertion into `OwnerWriteGuard` usage at write sites and drop the global scope). Smallest correct change: keep global scope (it is name-filtered and owner-checked) but replace the per-create `withoutOwnerScope()->find()` with `OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $order_id)` — one guarded lookup instead of unscoped-find-then-manual-compare.
- Breaking Change: NO
- Affected Packages: filament-jnt (`JntWebhookLogResource` reads — unchanged behavior), other `webhook_calls` users (spatie pipeline — untouched)
- Required Dependent Changes: none
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — `JntClient` retry uses blocking `usleep` in-request
- Severity: Low
- Location: `packages/jnt/src/Http/JntClient.php:46-70` (`microtime(true)*1000` timestamp, `usleep($retrySleep * 1000)` retry loop)
- Problem: Synchronous retries block the PHP worker (and Octane event loop slot) on carrier latency × attempts.
- Why It Matters: Checkout-path latency amplification when J&T degrades.
- Recommended Fix: Keep sync single-attempt for interactive paths; move retries to a queued `RetryJntRequest` job with backoff for async paths (`TrackParcel`, webhook-driven sync). No new retry library — Laravel queues + `Http::retry()` semantics already available.
- Breaking Change: NO
- Affected Packages: checkout/cart paths calling the client synchronously (behavior: faster failure, async recovery)
- Required Dependent Changes: none (internal call-site routing)
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs on the 4 domain tables, `getTable()` from `jnt.database.tables.*`, `json_column_type` present and consumed (including pg-conditional GIN handling in parcel/tracking-event migrations) — compliant.
- `JntServiceProvider` + `CartIntegrationRegistrar` auto-enable with `class_exists` checks — correct standalone/integrated behavior per package-boundary rules.
- `JntSpatieSignatureValidator` + `JntWebhookProfile` follow the spatie webhook-client idempotent-job pattern — compliant with the Spatie guidelines; verify webhook route is excluded from CSRF in host apps (documented in `docs/06-webhooks.md` — spot-check passed).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Direction correct; `BaseJntResource` is exemplary: `getNavigationGroup()` from config (final), `getNavigationSort()` from `filament-jnt.resources.navigation_sort.*`, `getEloquentQuery()` via `OwnerUiScope::apply()` honoring `jnt.owner.include_global`. Hold as the pattern.
- F1 — Authorization theater (Medium): `CancelOrderAction`, `SyncTrackingAction`, `PrintAwbTableAction` all use `->authorize(fn (): bool => Filament::auth()?->check() ?? false)` — any authenticated user passes; the real enforcement (`OwnerWriteGuard::findOrFailForOwner(JntOrder::class, ...)`) runs inside. Fix: replace the closure with a proper check (`$record` owner readability pre-check via `OwnerUiScope::canAccessRecord($record)` + relevant permission string, e.g. `jntOrder.cancel`), keeping the inner guard as defense-in-depth.
- F2 — `JntStatsAggregator::calculateOrderStats()` + `NavigationBadgeHelper::getNavigationBadge()` run aggregate/count queries per navigation render; acceptable for an ops dashboard but the badge path calls `static::getEloquentQuery()` per resource per render — ensure badge queries are `selectRaw` aggregates (they are) and do not hydrate models (verified: `first()` on aggregates only). No change.
- No domain leak beyond F1; `FilamentJntPlugin` navigation config nested correctly.

## Database Findings

- Migrations are idempotent-shaped; `000005_add_jnt_webhook_columns_to_webhook_calls_table.php` alters the shared table additively (nullable columns, no constraints) — safe pattern for shared tables, keep.
- Tracking-event volume: `jnt_tracking_events` grows per parcel per scan; confirm index on `['order_id'/'parcel_id','created_at']` supports the tracking timeline query (spot-check showed `tracking_number` indexes; add composite only with EXPLAIN evidence — not recommended blindly).

## Model / Domain Findings

- `JntOrder` minor-unit columns + integer casts, `JntOrderParcel::getVolume()`, `PackageInfoData` kg/cm DTOs with explicit carrier-decimal documentation — good domain hygiene after E2.
- `JntStatusMapper` + `StatusMappingStrategyRegistry` is the correct seam for carrier-status drift (tagged/registrar pattern per shared-foundations guidance); keep and use as the example against growth's ad-hoc strategies.
- `SendShipmentNotifications` correctly re-enters owner context (`OwnerContext::withOwner($owner, ...)`) — queued-notification exemplar.

## Security Findings

- Webhook signature validation via Spatie validator (`JntSpatieSignatureValidator`) — verify secret-missing behavior is fail-closed (503/401, mirroring signals' trusted-ingest posture) and covered by `tests/src/Jnt` webhook tests (53 files suggest yes — keep coverage, add missing-secret case if absent).
- `AwbController` (print waybill) must enforce the same `OwnerWriteGuard` lookup as the Filament `PrintAwbTableAction` — verify route-model binding does not resolve cross-owner `JntOrder` rows (use `OwnerRouteBinding::bind()` or guarded lookup; flagged as must-verify, one-line fix if open).
- No mass-assignment gaps found (DTOs + `$fillable` explicit).

## Performance Findings

- E4's per-create unscoped order lookup doubles webhook-write queries; the `OwnerWriteGuard` replacement keeps it at one guarded query — no extra index needed (`orders.id` PK lookup).
- `JntStatsAggregator` single aggregate query is optimal; badge rendering per nav item is inherent to Filament badges — no change.
- `TrackParcel` + `SyncTrackingAction` should debounce carrier polling per tracking number (cache `last_polled_at`, skip if <N minutes) — carrier-rate-limit protection; small additive change in `JntTrackingService`.

## Testing Findings

- `tests/src/Jnt/` (53) is the deepest in this review set; `tests/src/FilamentJnt/` (11) adequate. Gaps: E2 integer-math lock test; F1 authorization test (authenticated user from tenant B cannot cancel tenant-A order — expect 404, not 403/success); `AwbController` cross-owner test; `JntClient` single-attempt interactive behavior after Q1. Run: `./vendor/bin/pest --parallel tests/src/Jnt tests/src/FilamentJnt`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| cart, shipping | `JntShippingDriver`, multiplier config, cart manager | E2 config key change + E3 collapse | Update multiplier key to `_bp`; use `JntShippingCalculator` |
| signals | jnt domain events | E1 consolidation (subscribe to `TrackingUpdatedEvent`) | Update event subscriptions |
| checkout | `JntClient` sync behavior | Q1 async retries | None (faster failure + queued recovery) |
| host apps | deleted event classes, manager class | E1+E3 breaking | Update subscribers/imports (sweep `Jnt\\Events\\(TrackingUpdated|Parcel|TrackingStatusReceived)`, `CartManagerWithJntShipping`) |

## Recommended Refactor Plan (ordered steps)

1. E2: integer basis-point money math + lock test.
2. F1 + `AwbController` binding: real authorization (same pass, security).
3. E4: guarded order lookup in `JntWebhookLog::creating`.
4. E1: canonicalize tracking events; update dispatch sites + tests; delete superseded classes.
5. E3: collapse cart layers; update registrar.
6. Q1 + polling debounce; add required tests; run `./vendor/bin/pest --parallel tests/src/Jnt tests/src/FilamentJnt`.

## Files Likely to Change

- `packages/jnt/src/Events/*.php`, `src/Webhooks/ProcessJntWebhook.php`, `src/Shipping/JntShippingDriver.php`, `src/Cart/*.php`, `src/Support/Integrations/CartIntegrationRegistrar.php`, `src/Http/JntClient.php`, `src/Models/JntWebhookLog.php`, `src/Services/JntTrackingService.php`, `src/Http/Controllers/AwbController.php`, `config/jnt.php`
- `packages/filament-jnt/src/Actions/{CancelOrderAction,SyncTrackingAction,PrintAwbTableAction}.php`
- `tests/src/Jnt/Unit/Events/EventsCoverageTest.php`, `tests/src/Jnt/Unit/Webhooks/ProcessJntWebhookTest.php`, `tests/src/Jnt/Feature/Events/TrackingUpdatedEventTest.php`

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/jnt/src/Events/TrackingUpdated.php` (superseded by typed `TrackingUpdatedEvent` — both verified live; migrate the 2 `ProcessJntWebhook` dispatch sites + `ProcessJntWebhookTest`/`EventsCoverageTest` first, re-grep `Events\\TrackingUpdated[^E]` before deleting)
- `packages/jnt/src/Events/ParcelDelivered.php`, `ParcelInTransit.php`, `ParcelOutForDelivery.php`, `ParcelPickedUp.php`, `TrackingStatusReceived.php` (folded into status-driven handling of `TrackingUpdatedEvent`; verify no outside subscriptions via rg `Jnt\\Events\\Parcel|TrackingStatusReceived` before deleting)
- `packages/jnt/src/Cart/CartManagerWithJntShipping.php`, `src/Cart/JntShippingConditionProvider.php` (folded into `JntShippingCalculator`; verify references via rg before deleting)
- `->authorize(fn (): bool => Filament::auth()?->check() ?? false)` closures in the three filament actions (replaced by real checks; the inner `OwnerWriteGuard` stays)
- Nothing else: `JntWebhookLog` stays (fixed, not removed); `JntStatusMapper` registry stays (correct seam)

## Final Recommended Architecture

jnt exposes one typed event per occurrence, integer money math, one calculator behind registrar wiring, guarded webhook logging on the shared table, and a thin filament adapter whose actions authorize (not merely authenticate) before delegating to owner-guarded domain Actions.
