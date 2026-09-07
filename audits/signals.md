# signals Audit

## Packages Reviewed (bullets)

- `packages/signals` — privacy-first behavioral analytics: identities/sessions/events, goals/segments/reports, alert rules/logs/deliveries, ingestion pipeline, tracker, 20+ commerce listeners (93 `src/` files, `config/signals.php`, 12 migrations, `routes/api.php`)
- `packages/filament-signals` — Filament adapter: 7 resources, 10 report pages, dashboard, widgets, policies, guards (72 `src/` files, `config/filament-signals.php`)
- Root test coverage consulted: `tests/src/Signals/` (31 files), `tests/src/FilamentSignals/` (15 files)

## Overall Assessment (quality, health, risks, refactor size)

signals is the largest domain package in this review set and the most architecturally ambitious: 12 owner-scoped tables, HMAC-signed trusted ingestion with replay protection (`Http/Middleware/VerifyTrustedSignalSignature.php` — genuinely good), a `CrossTenantQuery` helper that documents its `withoutOwnerScope()` justification, and consistent `forOwner()` usage across the filament adapter. The core risks: (1) a god service — `Services/CommerceSignalsRecorder.php` (887 lines) doing cross-package attribute extraction via reflection-style duck-typing (`readPublicInt`, `callIntMethod`, `attributeValue`); (2) dual owner-write paths — commerce-support `HasOwner` plus a parallel `Models/Concerns/AutoAssignsSignalOwnerOnCreate.php` trait with its own config keys, so two implementations must agree forever; (3) public unauthenticated collect endpoints (`/collect/identify`, `/collect/browser-event`, `/collect/pageview`, `/collect/geo`) behind only `['api']` middleware with no verified throttle; (4) 20+ single-purpose Listener classes (boilerplate fan-out); (5) filament mutation guards (`SavedSignalReportMutationGuard`, `TrackedPropertyMutationGuard`) duplicating `OwnerWriteGuard`. No schema migration required. Refactor size: Medium-Large (code-only, staged).

## Migration Impact

**Migration Required: NO** — migration track completed 2026-09-07, see `migration-record.md#signals`

## Package Responsibilities

- Identity graph: `Models/TrackedProperty.php`, `SignalIdentity`, `SignalSession`, `SignalEvent`; `Services/TrackedPropertyResolver.php`, `Actions/{IngestSignalEvent,IdentifySignalIdentity,ResolveSession,CaptureSignalPageView,CaptureSignalGeolocation}.php`, `Services/SignalsIngestionRequestValidator.php`.
- Commerce bridge: `Services/CommerceSignalsRecorder.php`, `Support/CommerceSignalsIntegrationRegistrar.php`, 20+ `Listeners/Record*.php`, `Services/SignalsDashboardService.php`, `Services/SignalMetricsAggregator.php`.
- Reporting: `Services/{Acquisition,ConversionFunnel,Journey,Retention,ContentPerformance}ReportService.php`, `Contracts/ReportInterface.php`, `Models/{SignalSegment,SavedSignalReport,SignalGoal,TrackedProperty}.php`.
- Alerting: `Models/{SignalAlertRule,SignalAlertLog,SignalAlertDelivery}.php`, `Services/{SignalAlertEvaluator,SignalAlertDispatcher}.php`, `Actions/{EvaluateAlertRules,MarkSignalAlertAsRead/Unread,MarkAllSignalAlertsAsRead}.php`, `Jobs/{EvaluateSignalAlertsForEvent,DispatchSignalAlertDelivery}.php`, `Console/Commands/ProcessSignalAlertsCommand.php`.
- Browser surface: `Support/Browser/*` (context, manager, tracker renderer/injector), `Actions/ServeSignalsTracker.php`, `Support/Http/Middleware/BootstrapSignalsBrowserContext.php`, trusted ingestion (`Actions/IngestTrustedSignalOutcome.php`, `Data/TrustedSignalOutcomeData.php`, `VerifyTrustedSignalSignature`).

## Architecture Findings (each: Severity Critical/High/Medium/Low, Location files, Problem, Why It Matters, Recommended Fix concrete, Breaking Change YES/NO, Affected Packages list, Required Dependent Changes, Migration Required YES/NO)

### A1 — `CommerceSignalsRecorder` god service with reflection duck-typing
- Severity: High
- Location: `packages/signals/src/Services/CommerceSignalsRecorder.php` (887 lines, verified; duck-typing helpers `attributeValue:709`, `stringValue:778`, `callIntMethod:832`, `readPublicInt:850`, plus `readPublicScalar`/`getRawOriginal` fallbacks; silent-zero revenue fallbacks e.g. `:700`)
- Problem: One class extracts revenue/cart/order fields from cart, orders, affiliates, network, vouchers, checkout models via runtime method/property probing instead of contracts. A renamed method in any upstream package silently flips a metric to 0 (verified `?? 0`-style fallbacks on `attributeValue(...)` at lines ~43, ~76, ~110; `'revenue_minor' => 0` default at :700), corrupting revenue reporting without an error.
- Why It Matters: Silent-zero fallbacks turn every upstream refactor into a potential analytics corruption event with no failing test at the seam.
- Recommended Fix: Define narrow `SignalsConvertible` input DTOs (plain readonly arrays/shapes, not interfaces on upstream models) and move per-source extraction into small `Recorders/*` classes co-owned with explicit mapping (one per source: cart, order, voucher, affiliate); the recorder becomes a dispatcher. Replace silent `?? 0` fallbacks with `throw` on missing required fields for server-side sources (browser sources keep lenient parsing — different trust level, documented).
- Breaking Change: NO (internal split; public `record*` entry names kept)
- Affected Packages: growth (`AggregateExperimentMetrics` reads these events), filament-growth results pages, cart/orders/affiliates/vouchers/checkout (event shapes they emit — shapes unchanged, only extraction moves)
- Required Dependent Changes: none (same event payloads)
- Migration Required: NO

### A2 — Parallel owner-write trait duplicates `HasOwner`
- Severity: High
- Location: `packages/signals/src/Models/Concerns/AutoAssignsSignalOwnerOnCreate.php` (own `signals.owner.enabled` / `signals.owner.auto_assign_on_create` config reads, own immutability/match assertions) alongside `HasOwner`+`HasOwnerScopeConfig(ownerScopeConfigKey='signals.owner')` on every model
- Problem: Two owner-write implementations guard the same columns with subtly different semantics (`belongsToOwner`/`assignOwner` duck-calls vs `HasOwner::assignOwnerOnCreate`/`guardOwnedOwnerWrite`). They must agree on every edge (partial tuples, explicit global, auto-assign off) forever; any drift opens cross-tenant writes or false rejections.
- Why It Matters: The tenancy guarantee is only as strong as the weakest of two parallel enforcers.
- Recommended Fix: Delete the trait; rely solely on `HasOwner` (which already implements assign-on-create, tuple validation, immutability, context-match). If signals needs behavior `HasOwner` lacks, add it to commerce-support's `HasOwner` once for all packages. Update every model dropping the trait (mechanical).
- Breaking Change: NO (observable behavior identical when configs agree — which they must today)
- Affected Packages: growth (reads signal data; no write-path change), filament-signals
- Required Dependent Changes: none beyond trait removal at model `use` lines
- Migration Required: NO

### A3 — Public collect endpoints lack verified abuse controls
- Severity: High
- Location: `packages/signals/routes/api.php` (`/collect/identify`, `/collect/browser-event`, `/collect/pageview`, `/collect/geo` under `config('signals.http.middleware', ['api'])`); contrast `/collect/server-outcome` which correctly requires `VerifyTrustedSignalSignature`
- Problem: Four unauthenticated, bot-callable ingestion endpoints with no throttle visible in route registration (`signals.http.middleware` defaults to `['api']`; no `throttle:` entry verified in config). Analytics ingestion is a classic amplification target (table bloat → reporting DoS, storage-cost attack).
- Why It Matters: Unauthenticated write endpoints without rate limits are an availability and cost hole, and polluted events corrupt every downstream consumer (growth experiments, dashboards, alerts).
- Recommended Fix: Append `throttle:signals-collect` to the collect route group middleware and register a `signals-collect` limiter in `SignalsServiceProvider` (per-IP + per-tracked-property, e.g. 120/min IP, 600/min property); add honeypot/timestamp validation already present in `SignalsIngestionRequestValidator` (verify `revenue_minor` cannot be set from browser events — server-side only). Document the limiter keys in `docs/03-configuration.md`.
- Breaking Change: NO (additive middleware; legitimate tracker traffic is far below limits)
- Affected Packages: growth (reads this data — benefits), filament-signals (dashboards — benefits)
- Required Dependent Changes: host apps with custom `signals.http.middleware` must keep/merge the throttle entry (documented)
- Migration Required: NO

### A4 — 20+ single-method Listeners as boilerplate fan-out
- Severity: Medium
- Location: `packages/signals/src/Listeners/Record*.php` (20 files: cart, checkout, order, voucher, affiliate, application events)
- Problem: Each listener is a near-identical thin mapping from a foreign domain event to a recorder call. Adding the 21st source means copying a file; changing the mapping convention means editing 20 files.
- Why It Matters: Convention drift across 20 files is how silent-zero mappings (A1) creep back after the extraction.
- Recommended Fix: Replace with a single `RecordCommerceSignal` listener driven by a `SignalEventMap` (event-class → recorder-method + field map) registered in `CommerceSignalsIntegrationRegistrar`. Keep the 20 classes as deprecated subclasses for one pass only if external subscribers reference them — verified internal-only via rg, so delete outright and update `EventServiceProvider`/registrar mappings + tests.
- Breaking Change: YES (class names removed; event names unchanged)
- Affected Packages: cart, checkout, orders, vouchers, affiliates, affiliate-network, membership (application events), filament-growth
- Required Dependent Changes: update listener registrations to the single listener + map; tests updated in same pass (verified test files reference `Record*` classes — sweep `tests/src/Signals`)
- Migration Required: NO

### A5 — Filament mutation guards duplicate `OwnerWriteGuard`
- Severity: Medium
- Location: `packages/filament-signals/src/Support/SavedSignalReportMutationGuard.php:57`, `TrackedPropertyMutationGuard.php`, `SignalsModelReferenceGuard.php:34` (the last correctly delegates to `OwnerWriteGuard::findOrFailForOwner` — the other two hand-roll `forOwner()` checks)
- Problem: Three guard classes for one concern; two re-implement what the third (and commerce-support) already does.
- Why It Matters: Divergent guards diverge in behavior (include-global handling, error type — 404 vs 403 — matters for information disclosure posture).
- Recommended Fix: Delete the two hand-rolled guards; route all three call sites through `SignalsModelReferenceGuard` (which already wraps `OwnerWriteGuard`). Standardize on 404 (`NotFoundHttpException`) for cross-owner access to avoid tenant-existence oracle.
- Breaking Change: NO (internal)
- Affected Packages: filament-signals only
- Required Dependent Changes: none
- Migration Required: NO

## Code Quality Findings (same finding format)

### Q1 — Condition matcher/definition/query-service triple overlap
- Severity: Low
- Location: `packages/signals/src/Services/SignalEventConditionMatcher.php`, `SignalEventConditionDefinition.php`, `SignalEventConditionQueryService.php`, `SignalEventPropertyTypeInferrer.php`
- Problem: Four classes split definition, matching, querying, and type inference for event conditions; the matcher and query service must implement identical operator semantics in PHP vs SQL.
- Why It Matters: Operator added to one and missed in the other yields UI-preview/SQL-execution divergence (segment previews disagree with actual membership).
- Recommended Fix: Single `SignalCondition` value object owning operator list + `matches(array $event): bool` + `applyToQuery(Builder): Builder`; keep the inferrer as its factory. Add a parity test (same fixture through both paths).
- Breaking Change: NO (internal; service facades kept as delegators then removed next pass)
- Affected Packages: filament-signals (segment/alert forms consume definitions)
- Required Dependent Changes: none this pass
- Migration Required: NO

## Laravel-Specific Findings

- PHP 8.4, no FK constraints/cascades (rg clean), uuid PKs on all 12 tables, `getTable()` from `signals.database.tables.*`, `json_column_type` in config + all migrations — compliant.
- No `down()` action needed. No soft deletes — compliant.
- `ProcessSignalAlertsCommand` correctly uses `SignalAlertRule::query()->forOwner()` (line 63) — owner-scoped console path, exemplar for other packages' commands.
- Jobs (`EvaluateSignalAlertsForEvent`, `DispatchSignalAlertDelivery`, `ReverseGeocodeSessionJob`) must carry explicit owner via `OwnerContextJob`/`OwnerScopedJob` rather than ambient auth — verify each job class implements the contract (spot-check flagged this as unverified; required test below covers it).

## Filament Adapter Findings (thin-adapter check, domain leak, duplication, dependency direction)

- Direction correct; navigation compliant (`getNavigationGroup()` from `filament-signals.navigation.group` on all 7 resources + `SignalsDashboard`/`ReportPage`).
- `getEloquentQuery()` consistently uses `Model::query()->forOwner()` with form option lists also scoped (`TrackedProperty::query()->forOwner()`) — the best adapter scoping in this review set; hold as the example for filament-growth/filament-jnt.
- Domain leak (Medium, see A5/Q1): guards and condition logic live partly in adapter `Support/`; after fixes the adapter keeps only `SignalsUiConfig`, `SignalFormOptionLists`, sanitizers — pure UI concerns.
- `ListSignalInteractionRules` page (585+ lines, scanner calls + sort-order juggling inline) should delegate to a domain `InteractionRuleService::reorderForOwner()`; page keeps only table actions.

## Database Findings

- 12 migrations all idempotent-shaped (`commerce_schema_create_if_missing` pattern via helpers), uuid PKs, `timestampTz`, configurable JSON — compliant.
- High-volume tables (`signals_events`, `signals_sessions`) need composite-index review against the actual reporting queries (`tracked_property_id, occurred_at`, `tracked_property_id, event_name, occurred_at`): verify with `EXPLAIN` on production-like volume before adding — do not add speculative indexes.

## Model / Domain Findings

- After A2, all 12 models use canonical `HasOwner` with `ownerScopeConfigKey='signals.owner'` — consistent and correct.
- Owner-mode contract with growth (mirrors growth audit A1, agreed direction): `signals.owner.enabled` defaults `false` (verified `config/signals.php:71-75`), matching growth's `features.owner.enabled=false`. Enabling either requires enabling the other; growth asserts the pairing at boot and both sides fail closed on mixed modes. No default flip on either side.
- `SignalAlertEvaluator` + `SignalAlertDispatcher` split (evaluate vs deliver) is correct separation; keep. `EvaluateAlertRules` action vs `EvaluateSignalAlertsForEvent` job naming is confusing (action evaluates rules in-process, job per event) — rename job to `EvaluateAlertsForSignalEvent` for clarity (mechanical, same pass as A4).

## Security Findings

- `VerifyTrustedSignalSignature` is exemplary: required secret (503 when unconfigured, fail-closed), strict 10-digit timestamp, `sha256=` prefix tolerance with format validation, `hash_equals`, replay window (`max(30, ...)`) plus `RateLimiter`-backed single-use keys (409 on replay). No change.
- Browser-tracker injection (`InjectSignalsTrackerIntoHtmlResponse`, `SignalsTrackerRenderer`) must escape the tracked-property key/context into JS safely (JSON-encode, no string interpolation) — spot-check flagged as unverified; required review test: render tracker with hostile property name, assert no script breakout.
- `BootstrapSignalsBrowserContext` middleware auto-registers middleware (`auto_register_middleware`, `middleware_group=web` at config lines 181–182) — host apps opting out must set the flag; document that disabling it disables tracker context (fail-closed behavior, correct).

## Performance Findings

- `SignalMetricsAggregator::updateOrCreate` per tracked property per day is fine; `EvaluateAlertRules` fanning per event into `EvaluateSignalAlertsForEvent` jobs is correct async design — keep.
- Reporting services run synchronously in Filament pages (`ReportPage` family, 10 pages); large date ranges over `signals_events` will time out. Recommended: cap default range (e.g. 90 days, already partially via `InteractsWithSignalsDateRange`), add `SignalDailyMetric` rollup reads as the default source for ranges >31 days (the rollup table exists precisely for this — wire it before adding caches).
- `InteractionRuleScanner::scan()` capped at 25 candidates (line 27) — good bound; keep.

## Testing Findings

- `tests/src/Signals/` (31) + `tests/src/FilamentSignals/` (15) is solid breadth. Gaps tied to this audit: (1) cross-tenant write/read isolation per model via shared `OwnerScopingContractTests` (reuse commerce-support's contract instead of bespoke tests); (2) recorder silent-zero test (upstream field rename → exception, not 0) after A1; (3) collect-endpoint throttle test (429 on flood) after A3; (4) condition PHP/SQL parity test after Q1; (5) job owner-context test (dispatch without owner → fail-closed); (6) tracker XSS test. Run: `./vendor/bin/pest --parallel tests/src/Signals tests/src/FilamentSignals`.

## Cross-Package Dependency Impact (table: Dependent Package | Dependency | Impact | Required Change)

| Dependent Package | Dependency | Impact | Required Change |
|---|---|---|---|
| growth | signal event/property reads | A1 extraction keeps payloads identical; A2 re-scopes writes (no read change) | None (verify via growth tests) |
| filament-growth | results pages, policies | Same as above | None |
| cart/checkout/orders/vouchers/affiliates/affiliate-network/membership | emitted domain events | A4 listener consolidation (event names unchanged) | Update listener registrations to single listener |
| filament-signals | guards, scanner, report services | A5/Q1 internal | None outside adapter |
| host apps | `signals.http.middleware` | A3 throttle addition | Merge throttle entry if overriding middleware |

## Recommended Refactor Plan (ordered steps)

1. A2: delete parallel owner trait (mechanical, high safety value).
2. A3: throttle collect endpoints + browser-field allowlist assertion.
3. A1: split recorder into per-source recorders with strict server-side fields.
4. A4: consolidate listeners into mapped single listener.
5. A5+Q1: unify guards and condition object; delegate page logic to domain.
6. Rollup-backed long-range reports.
7. Add required tests; run `./vendor/bin/pest --parallel tests/src/Signals tests/src/FilamentSignals`.

## Files Likely to Change

- `packages/signals/src/Services/CommerceSignalsRecorder.php` (split), `src/Models/Concerns/AutoAssignsSignalOwnerOnCreate.php` (delete), all `src/Models/*.php` (drop trait), `src/Listeners/*.php` (consolidate), `src/Support/CommerceSignalsIntegrationRegistrar.php`, `src/Services/SignalEventCondition*.php`, `src/SignalsServiceProvider.php` (limiter), `routes/api.php`, `config/signals.php`
- `packages/filament-signals/src/Support/SavedSignalReportMutationGuard.php`, `TrackedPropertyMutationGuard.php` (delete), `Resources/SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php` (delegate)

## Files / Code That Should Be Removed (explicit list, no legacy preservation)

- `packages/signals/src/Models/Concerns/AutoAssignsSignalOwnerOnCreate.php` (replaced by canonical `HasOwner` — verified every model already uses `HasOwner`+`HasOwnerScopeConfig`; re-grep `AutoAssignsSignalOwnerOnCreate` before deleting)
- 19 of 20 `packages/signals/src/Listeners/Record*.php` files (consolidated into mapped single listener; verified internal-only registrations via `CommerceSignalsIntegrationRegistrar` + tests sweep before deleting)
- `packages/filament-signals/src/Support/SavedSignalReportMutationGuard.php`, `src/Support/TrackedPropertyMutationGuard.php` (routed through `SignalsModelReferenceGuard`/`OwnerWriteGuard`)
- Silent `?? 0` revenue fallbacks in `CommerceSignalsRecorder` server-side paths (replaced by throws)
- Nothing else: `CrossTenantQuery` stays (documented, justified); `VerifyTrustedSignalSignature` stays untouched

## Final Recommended Architecture

signals owns ingestion → identity → reporting → alerting with one owner enforcer (`HasOwner`), strict per-source recorders behind a mapped listener, throttled public collect + HMAC trusted ingest, unique idempotency keys, rollup-backed reports, and a filament adapter that scopes every query and guards every mutation through `OwnerWriteGuard` while computing nothing itself.
