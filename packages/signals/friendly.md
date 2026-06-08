# Signals friendliness review

This note reviews `packages/signals` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (25+ classes)
- `src/Listeners` (15 classes)
- `src/Console/Commands` (2 classes)
- `src/Jobs` (2 classes)
- `src/Support`
- `src/Models` (10 classes)
- `src/Contracts` (2 classes)
- downstream consumers in `affiliates`, `cart`, `checkout`, `vouchers`, `orders`, `growth`

## What is already friendly

### Real geocoder contract

- `Contracts/ReverseGeocoderContract.php`
- `Services/Geocoders/NominatimGeocoder.php`

Geocoding is a real adapter seam. Adding a new provider (Google, Mapbox) is a new class.

### Real location resolver contract

- `Contracts/SignalLocationResolverContract.php`
- `Services/SignalLocationResolverPipeline.php`

Location resolution is a pipeline behind a contract. New resolver strategies can be added.

### Integration registrar isolates wiring

- `Support/CommerceSignalsIntegrationRegistrar.php`

The package plugs into the commerce ecosystem through a registrar, not by editing foundation.

### Listeners fan out from many commerce events

- 15 listeners reacting to cart, checkout, order, voucher, and affiliate events

This is the right shape for an analytics package — react to events, never reach into models.

### Background jobs handle async work

- `Jobs/EvaluateSignalAlertsForEvent.php`
- `Jobs/ReverseGeocodeSessionJob.php`

Long-running work is properly queued.

## Findings

### 1. Service count is very high (25+) with no Actions

**Files in `src/Services/`**

- `CommerceSignalsRecorder` (main ingest)
- `SignalsIngestionRequestValidator`
- `SignalsDashboardService`
- `TrackedPropertyResolver`
- `Signals/RouteCatalog`
- `SignalsEventConditionDefinition`
- `SignalsEventConditionMatcher`
- `SignalsEventConditionQueryService`
- `SignalsEventPropertyTypeInferrer`
- `SignalLocationResolverPipeline`
- `SignalMetricsAggregator`
- `SignalAlertEvaluator`
- `SignalAlertDispatcher`
- `SignalSegmentReportFilter`
- `SavedSignalReportDefinition`
- `SignalsIngestionRequestValidator`
- `SignalUserAgentParser`
- `Geocoders/NominatimGeocoder`
- `AcquisitionReportService`
- `ConversionFunnelReportService`
- `RetentionReportService`
- `JourneyReportService`
- `PageViewReportService`
- `ContentPerformanceReportService`
- `DevicesReportService`
- `GoalsReportService`
- `LiveActivityReportService`

**Why this hurts friendliness**

This is the largest service count in the monorepo. Many of these are read-side (reports) which is fine, but the ingestion and recording paths (recorder, validator, property type inferrer) are likely candidates for Actions.

**Recommendation**

Group services by domain:

- `Services/Ingestion/` (recorder, validator, property type inferrer)
- `Services/Reports/` (all the report services)
- `Services/Alerts/` (evaluator, dispatcher)
- `Services/Properties/` (definition, matcher, query, tracked property resolver)
- `Services/Geocoding/` (geocoder + pipeline)

Move mutations (record signal, evaluate alert, dispatch alert) to Actions:

- `Actions/RecordSignal`
- `Actions/EvaluateAlertRule`
- `Actions/DispatchAlert`

### 2. Listeners are large and likely do real work

**Files**

- 15 listeners under `src/Listeners/` including `RecordOrderPaidSignal`, `RecordCartAbandonedSignal`, `RecordCheckoutCompletedSignal`, etc.

**Why this hurts friendliness**

The listener count is high. Each listener likely maps a commerce event into a signal. If the mapping logic is inline, the package has 15 places to update when the signal model changes.

**Recommendation**

Extract a `MapCommerceEventToSignal` interface and one mapper per event family. The listeners become thin event adapters. This shrinks the listener surface to a small adapter per event.

### 3. Report services are 8 sibling classes

**Files**

- `AcquisitionReportService`
- `ConversionFunnelReportService`
- `RetentionReportService`
- `JourneyReportService`
- `PageViewReportService`
- `ContentPerformanceReportService`
- `DevicesReportService`
- `GoalsReportService`
- `LiveActivityReportService`

**Why this hurts friendliness**

This is the most report-heavy package in the monorepo. Each report is its own class. New report types will keep being added.

**Recommendation**

Add a `Reports/ReportInterface` and a registry. Each report registers itself. The dashboard service queries the registry for available reports.

### 4. `CommerceSignalsRecorder` is a likely catch-all orchestrator

**Files**

- `src/Services/CommerceSignalsRecorder.php`

**Why this hurts friendliness**

Like `AffiliateService` and `OrderService`, this is probably a single class that owns many operations. Every event listener and job calls it.

**Recommendation**

Audit for opportunity to delegate to Actions. Keep it as a compatibility facade but move mutations to the new Action tree.

### 5. Console commands duplicate owner-loop logic

**Files**

- `src/Console/Commands/AggregateDailyMetricsCommand.php`
- `src/Console/Commands/ProcessSignalAlertsCommand.php`

**Why this hurts friendliness**

The `ProcessSignalAlertsCommand` is the same pattern as the affiliates commands. Cross-package evidence is already strong (affiliates friendly.md cites this file).

**Recommendation**

Use `commerce-support`'s `OwnerBatchRunner` (when it lands) for both commands. Migrate the affiliates commands at the same time. Add characterization tests first.

### 6. Browser context is in `Support/` but not clearly a contract

**Files**

- `Support/Browser/SignalsBrowserContextManager.php`
- `Support/Browser/SignalsBrowserContext.php`
- `Support/Browser/InjectSignalsTrackerIntoHtmlResponse.php`
- `Support/Browser/SignalsTrackerRenderer.php`
- `Support/Http/Middleware/BootstrapSignalsBrowserContext.php`

**Why this hurts friendliness**

The browser context is split across 5 files. Adding a new context source (e.g., a different header scheme or signed payload) means editing all of them.

**Recommendation**

Extract a `BrowserContextResolver` contract. The manager and renderer become thin adapters. The resolver owns context source priority.

### 7. `Events/` is empty (the package consumes, not produces)

**Files**

- (none)

**Why this is worth noting**

This is the right shape for an analytics package. No outbound events, only inbound listeners. Keep this discipline.

### 8. Condition matching is a real engine

**Files**

- `Services/SignalsEventConditionDefinition.php`
- `Services/SignalsEventConditionMatcher.php`
- `Services/SignalsEventConditionQueryService.php`

This is data-driven and good. Consider promoting the definition/matcher/query to a `ConditionEngine` namespace for clarity.

## Concrete refactor plan

### Phase 1 — group services by domain

**Steps**

1. Create `Services/Ingestion/`, `Services/Reports/`, `Services/Alerts/`, `Services/Properties/`, `Services/Geocoding/` subfolders.
2. Move related services.
3. Update imports.

### Phase 2 — extract mutations to Actions

**Steps**

1. Move ingestion, alert evaluation, and alert dispatch to Actions.
2. Update listeners, jobs, and console commands.
3. Add tests for each Action.

### Phase 3 — extract event-to-signal mapping

**Steps**

1. Add `Events/MapCommerceEventToSignalInterface`.
2. Add one mapper per event family.
3. Listeners become thin adapters.

### Phase 4 — add report registry

**Steps**

1. Add `Reports/ReportInterface` and a registry.
2. Register all 8 built-in reports.
3. Update the dashboard service to use the registry.

### Phase 5 — adopt owner-batch helper

**Steps**

1. Wait for `commerce-support`'s `OwnerBatchRunner`.
2. Migrate `AggregateDailyMetricsCommand` and `ProcessSignalAlertsCommand`.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — group services by domain

- [pending] Create `Services/Ingestion/`, `Services/Reports/`, `Services/Alerts/`, `Services/Properties/`, `Services/Geocoding/` ...
- [pending] Move related services.
- [pending] Update imports.

### Phase 2 — extract mutations to Actions

- [pending] Move ingestion, alert evaluation, and alert dispatch to Actions.
- [pending] Update listeners, jobs, and console commands.
- [pending] Add tests for each Action.

### Phase 3 — extract event-to-signal mapping

- [pending] Add `Events/MapCommerceEventToSignalInterface`.
- [pending] Add one mapper per event family.
- [pending] Listeners become thin adapters.

### Phase 4 — add report registry

- [pending] Add `Reports/ReportInterface` and a registry.
- [pending] Register all 8 built-in reports.
- [pending] Update the dashboard service to use the registry.

### Phase 5 — adopt owner-batch helper

- [pending] Wait for `commerce-support`'s `OwnerBatchRunner`.
- [pending] Migrate `AggregateDailyMetricsCommand` and `ProcessSignalAlertsCommand`.



## Suggested verification scope

- per-Action tests for new mutation Actions
- listener tests
- report tests
- cross-package tests for affiliates/cart/checkout/vouchers/orders/growth after refactor

## Recommended first move

Phase 5 — adopt the owner-batch helper. This is the smallest, highest-leverage change because the duplication with affiliates is already proven and the helper lives in foundation. The other phases can follow.
