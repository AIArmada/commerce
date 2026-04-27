## Plan: Signals-Owned Cart Intelligence

Move cart recovery, cart-local alerts, and cart-local metrics out of `packages/cart` and `packages/filament-cart`. `packages/cart` stays lightweight and emits core domain events; `packages/filament-cart` keeps live cart snapshots and emits operational events; `packages/signals` becomes the optional analytics/alert engine when installed and explicitly enabled; `packages/filament-signals` owns the admin UI for analytics and alert rules.

This plan also hardens the owner/multitenancy foundation in `packages/commerce-support` first, because cart/signals cleanup depends on reliable, reusable owner semantics.

**Steps**

### Phase 0 — Safety and scope

1. Preserve the current working tree exactly. There are many existing uncommitted changes outside this task, especially in affiliate packages and owner-scoping work. Do not run cleanup/revert commands; do not delete unrelated files; do not format repo-wide.
2. Immediate implementation scope is limited to `commerce-support`, `signals`, `filament-signals`, `cart`, and `filament-cart`.
3. Create a detailed follow-up playbook for all other tenant packages at `/Users/Saiffil/Herd/commerce/docs/audit/owner-multitenancy-hardening-plan.md`. This document must include enough specific instructions, checklists, patterns, and examples for later implementation without re-research.
4. Create Signals manual migration instructions at `/Users/Saiffil/Herd/commerce/docs/development/signals-manual-migrations.md` for the single existing deployment / packages already using Signals.

### Phase 1 — Harden commerce-support ownership foundation

5. Keep and harden the uncommitted `OwnerContext::hasOverride()` and `OwnerContext::isExplicitGlobal()` APIs in `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerContext.php`.
6. Treat `OwnerContext::withOwner(null, ...)` as the required explicit-global context when owner mode is enabled. A null resolver without explicit global override should fail fast for owner-protected paths.
7. Keep `OwnerContext::override()` / `clearOverride()` as low-level APIs for tests/bootstrap/system integration, but document and prefer `withOwner()` for application code. Add nested restoration tests.
8. Keep `OwnerContext::fromTypeAndId()` cheap and non-querying. It should validate class/morph type but should not require the owner row to exist. Add a separate strict helper only if a call site needs owner existence validation.
9. Standardize owner config shape across immediate packages to top-level `owner.enabled`, `owner.include_global`, and `owner.auto_assign_on_create` where applicable. This includes moving Signals away from long-term reliance on `signals.features.owner`.
10. Make `includeGlobal` explicit-call driven in `HasOwner::scopeForOwner()` and `OwnerQuery`: `forOwner($owner)` means owner-only; `forOwner($owner, includeGlobal: true)` means owner plus global rows; `globalOnly()` means only null-owner rows. Package config may control ambient/global-scope defaults, but must not silently negate explicit `includeGlobal: true`.
11. Default `include_global` to false for owner-enabled packages.
12. Move missing-owner fail-fast into `commerce-support` core scopes/helpers rather than duplicating it per package. Owner-enabled read/write helpers should require an owner or explicit global context.
13. Strengthen `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerWriteGuard.php` and `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerQuery.php` as the standard tools for submitted-ID and foreign-key validation.
14. Standardize route model binding for tenant-owned models through `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerRouteBinding.php` or a successor. Default Laravel route binding must not be used for `HasOwner` models.
15. Add a reusable hidden owner-scope uniqueness helper/trait in `commerce-support` for tables that need database uniqueness with nullable owner columns. This replaces public/confusing `owner_key` patterns. The hidden key is computed from `owner_type`/`owner_id`, is not user-fillable/exported, and is never used as an authorization/read boundary.
16. Add reusable owner-scope contract tests and grep/static verification guidance in commerce-support for Eloquent scopes, query-builder paths, route bindings, write guards, global-row write protection, explicit-global semantics, and nested `OwnerContext::withOwner()` behavior.
17. Enforce global-row write semantics: global rows may be visible/usable in owner context when explicitly included, but updates/deletes to global rows require explicit global context or a deliberate system operation.

### Phase 2 — Enhance Signals as the optional intelligence engine

18. Update `/Users/Saiffil/Herd/commerce/packages/signals/config/signals.php` with explicit opt-in integration sections: `signals.integrations.cart` for core cart events and `signals.integrations.filament_cart` for filament-cart operational events. Installing Signals alone must not enable cart capture.
19. Add generic alert filter support to Signals alert rules. Use reusable JSON filters/conditions for event names, event categories, tracked property scope, and event property conditions. Do not add cart-specific alert columns.
20. Add generic multi-channel alert dispatch in Signals: database, email, webhook, and Slack or equivalent. Use named configured destinations by default; inline per-rule endpoints should be opt-in only.
21. Add a generic idempotency/source-event key to `SignalEvent`, unique per tracked property when present, so live listeners and backfills can be retried safely.
22. Update existing Signals migrations directly for clean beta schema rather than adding upgrade migrations. Affected migrations likely include `2001_01_01_000001_create_signals_tracked_properties_table.php`, `2001_01_01_000004_create_signals_events_table.php`, `2001_01_01_000008_create_signals_alert_rules_table.php`, and `2001_01_01_000009_create_signals_alert_logs_table.php`. Apply the hidden owner-scope uniqueness helper to Signals tables with owner-nullable unique slugs where needed.
23. Update Signals models and services: `SignalEvent`, `SignalAlertRule`, `SignalAlertLog`, `SignalAlertEvaluator`, `SignalAlertDispatcher`, `SignalMetricsAggregator`, and `CommerceSignalsRecorder`.
24. Add default tracked-property provisioning when cart/commerce integration is explicitly enabled. Resolve or auto-create one deterministic property per owner/global context; if multiple active properties match, require config selection instead of guessing.
25. Keep event capture privacy-first. Record operational/numeric fields by default; exclude raw PII such as email, phone, names, and full cart metadata unless allowlisted in Signals config.
26. Keep scheduled/command alert evaluation as the baseline. Add optional on-ingest evaluation via config, queued by default to avoid request latency and webhook/email coupling.
27. Add a Signals-side manual cart backfill command. It should be optional via `class_exists`, owner-scoped, dry-run capable, idempotent, and never run automatically on install or enablement.
28. Signals owns optional integration by registering listeners via `class_exists` and config. `cart` and `filament-cart` must not import or call Signals directly.

### Phase 3 — Update filament-signals UI

29. Update `/Users/Saiffil/Herd/commerce/packages/filament-signals/src/Resources/SignalAlertRuleResource.php` and related pages/schemas so users can manage generic alert rules, filters, channels, named destinations, cooldowns, severity, priority, and active state.
30. Update `/Users/Saiffil/Herd/commerce/packages/filament-signals/src/Resources/SignalAlertLogResource.php` so logs display matched metric values, thresholds, event/filter context, channels notified, read state, and owner-safe linked context.
31. Keep analytics/alert admin ownership in `filament-signals`; do not keep wrapper/proxy analytics pages in `filament-cart` in this first implementation.
32. Add/update tests in `tests/src/FilamentSignals` for the enhanced rules/logs UI and owner-scoped access.

### Phase 4 — Clean packages/cart

33. Delete cart-local recovery, popup, metrics, and alert migrations from `/Users/Saiffil/Herd/commerce/packages/cart/database/migrations`: recovery outcomes, popup interventions, daily metrics, recovery campaigns, recovery templates, recovery attempts, alert rules, and alert logs.
34. Delete cart-local models and states: `CartDailyMetrics`, `RecoveryCampaign`, `RecoveryTemplate`, `RecoveryAttempt`, `AlertRule`, `AlertLog`, and all `RecoveryAttemptStatus` state classes under `/Users/Saiffil/Herd/commerce/packages/cart/src/States`.
35. Update `/Users/Saiffil/Herd/commerce/packages/cart/config/cart.php` to remove unused table config keys for alerts, metrics, recovery, outcomes, and popup interventions. Keep config minimal and actively used.
36. Keep core cart persistence, cart storage, cart events, conditions, and condition model support.
37. Remove public `owner_key` from `Condition` and its migration. If condition name uniqueness still needs nullable-owner database enforcement, use the new hidden commerce-support owner-scope uniqueness helper.
38. Remove owner-key helper APIs from `HasCartOwner` unless replaced by the commerce-support hidden uniqueness helper. `owner_type`/`owner_id` remain the only owner boundary.
39. Add Composer `suggest` entries in `/Users/Saiffil/Herd/commerce/packages/cart/composer.json` for optional `aiarmada/signals` capability.
40. Fix `/Users/Saiffil/Herd/commerce/packages/cart/database/migrations/2000_02_01_000006_add_performance_indexes_to_carts_table.php` so indexes match owner-scoped `DatabaseStorage::baseQuery()` patterns: owner-aware lookup by `owner_type`, `owner_id`, `identifier`, `instance`, plus owner-aware cleanup/activity indexes. Remove down-migration drops for indexes that are not created.
41. Update cart docs to remove stale recovery/alert/metrics configuration and describe optional Signals integration only as an optional ecosystem feature.

### Phase 5 — Clean packages/filament-cart

42. Delete cart-local alert, recovery, and analytics services, commands, pages, widgets, resources, settings, and views. This includes `AlertEvaluator`, `AlertDispatcher`, `CartMonitor` alert processing paths, `Recovery*` services/resources/widgets, `MetricsAggregator`, `CartAnalyticsService`, `ExportService`, `AnalyticsPage`, `RecoverySettingsPage`, `AlertRuleResource`, and `PendingAlertsWidget` once Signals replacement exists.
43. Keep live cart snapshot management, cart resources, cart item/condition resources, condition management, snapshot synchronization, and cart actions.
44. Keep a lean abandonment marker/detector command such as `cart:mark-abandoned`. It updates `checkout_abandoned_at` and emits `CartAbandoned`; it does not evaluate alert rules or dispatch notifications.
45. Remove `cart:monitor` and `cart:process-alerts` once Signals owns alert evaluation/dispatch.
46. Remove recovery-specific snapshot fields from `/Users/Saiffil/Herd/commerce/packages/filament-cart/database/migrations/2000_08_01_000001_create_cart_snapshots_table.php` and `/Users/Saiffil/Herd/commerce/packages/filament-cart/src/Models/Cart.php`: `recovery_attempts` and `recovered_at`. Keep live/abandonment fields: `last_activity_at`, `checkout_started_at`, `checkout_abandoned_at`, totals, item counts, currency, and metadata.
47. Remove public `owner_key` from cart snapshots. Use the new hidden commerce-support owner-scope uniqueness helper only for database uniqueness if needed.
48. Add/keep operational events with scalar payloads: `CartSnapshotSynced`, `CartCheckoutStarted`, `CartAbandoned`, and `HighValueCartDetected`.
49. Emit `CartSnapshotSynced` only when material persisted fields change, not on every no-op sync call.
50. Emit `HighValueCartDetected` from snapshot synchronization when cart total crosses a configurable high-value threshold from below to at/above threshold.
51. Use dotted lower-snake recorded Signals event names: `cart.snapshot.synced`, `cart.checkout.started`, `cart.abandoned`, and `cart.high_value.detected`.
52. Add Composer `suggest` entries in `/Users/Saiffil/Herd/commerce/packages/filament-cart/composer.json` for optional `aiarmada/filament-signals` admin analytics/alert UI.
53. Update filament-cart docs to remove recovery/local analytics/local alert docs and point analytics/alert management to optional `filament-signals`.

### Phase 6 — Clean tests and shared test schemas

54. Delete stale tests for removed cart/filament-cart recovery, metrics, and local alert features. Do not keep skipped legacy tests unless explicitly documenting a deferred migration path.
55. Update `/Users/Saiffil/Herd/commerce/tests/src/TestCase.php` to remove deleted cart/filament-cart tables and fields, and to apply the hidden owner-scope uniqueness helper patterns where test schemas need nullable-owner uniqueness.
56. Add replacement tests in Signals for generic alert filters, alert channels, named destinations, idempotency, on-ingest queued evaluation, default tracked-property provisioning, PII allowlisting, cart/filament-cart optional listeners, and manual backfill.
57. Add/expand commerce-support tests for explicit global semantics, fail-fast owner context, write guards, query-builder owner scoping, owner route binding, hidden owner-scope uniqueness helper, global write protection, and nested context restoration.
58. Update cart/filament-cart tests for snapshot sync events, abandonment marker, high-value threshold crossing, owner-aware performance indexes, no public `owner_key`, and no recovery/alert/metric remnants.

### Phase 7 — Documentation and rollout artifacts

59. Create `/Users/Saiffil/Herd/commerce/docs/audit/owner-multitenancy-hardening-plan.md`. It must include the all-tenant-package rollout plan, owner config standardization, query/write/route patterns, test checklist, grep checklist, migration notes, and package-by-package instructions.
60. Create `/Users/Saiffil/Herd/commerce/docs/development/signals-manual-migrations.md`. It must include exact manual migration instructions for existing Signals deployments and other packages using Signals, including new columns/indexes/config changes and validation steps.
61. Update existing package docs in `packages/cart/docs`, `packages/filament-cart/docs`, `packages/signals/docs`, `packages/filament-signals/docs` if present, and `commerce-support` docs if present. Also update repo upgrade/troubleshooting docs where stale.

**Relevant files**

- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerContext.php` — explicit-global APIs, scoped context behavior, cheap owner-reference resolution.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerQuery.php` — source of truth for Eloquent/query-builder owner constraints and explicit include-global semantics.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerScope.php` — default global scope behavior and fail-fast owner enforcement.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerWriteGuard.php` — central submitted-ID/FK validation helper to strengthen and standardize.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerRouteBinding.php` — standard route binding for tenant-owned models.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Traits/HasOwner.php` — `forOwner`, `globalOnly`, owner assignment and semantics.
- `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Traits/HasOwnerScopeConfig.php` and `/Users/Saiffil/Herd/commerce/packages/commerce-support/src/Support/OwnerScopeConfig.php` — owner config standardization.
- `/Users/Saiffil/Herd/commerce/packages/signals/config/signals.php` — explicit cart/filament-cart integrations, alert destination config, privacy allowlist, alert evaluation config, owner config.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Actions/IngestSignalEvent.php` — idempotent event ingestion, optional queued alert evaluation.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Services/CommerceSignalsRecorder.php` — optional cart/filament-cart event mapping, PII-safe payloads.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Support/CommerceSignalsIntegrationRegistrar.php` — `class_exists` listener registration for optional integrations.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Services/SignalAlertEvaluator.php` — generic filter/metric evaluation.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Services/SignalAlertDispatcher.php` — generic multi-channel alert dispatch.
- `/Users/Saiffil/Herd/commerce/packages/signals/src/Models/SignalEvent.php`, `SignalAlertRule.php`, `SignalAlertLog.php`, `TrackedProperty.php` — model/schema changes.
- `/Users/Saiffil/Herd/commerce/packages/filament-signals/src/Resources/SignalAlertRuleResource.php` and `SignalAlertLogResource.php` — UI replacement for cart-local alert UI.
- `/Users/Saiffil/Herd/commerce/packages/cart/config/cart.php` — remove stale local alert/recovery/metrics config and add optional Signals suggestion/docs alignment.
- `/Users/Saiffil/Herd/commerce/packages/cart/database/migrations/2000_02_01_000006_add_performance_indexes_to_carts_table.php` — owner-aware index fix.
- `/Users/Saiffil/Herd/commerce/packages/cart/src/Models/Condition.php` and `/Users/Saiffil/Herd/commerce/packages/cart/database/migrations/2000_02_01_000003_create_conditions_table.php` — remove public owner-key and adopt hidden owner-scope uniqueness if needed.
- `/Users/Saiffil/Herd/commerce/packages/filament-cart/src/Models/Cart.php` and `/Users/Saiffil/Herd/commerce/packages/filament-cart/database/migrations/2000_08_01_000001_create_cart_snapshots_table.php` — remove owner-key/recovery fields; keep live/abandonment snapshot data.
- `/Users/Saiffil/Herd/commerce/packages/filament-cart/src/Services/NormalizedCartSynchronizer.php` — emit material snapshot/high-value events and remove recovery-attempt preservation.
- `/Users/Saiffil/Herd/commerce/packages/filament-cart/src/Commands/MarkAbandonedCartsCommand.php` — keep lean abandonment marker and emit `CartAbandoned`.
- `/Users/Saiffil/Herd/commerce/packages/filament-cart/src/FilamentCartServiceProvider.php` and `FilamentCartPlugin.php` — unregister removed services/commands/pages/resources/widgets.
- `/Users/Saiffil/Herd/commerce/tests/src/TestCase.php` — remove deleted table schemas/fields and update owner test scaffolding.

**Verification**

1. Run targeted commerce-support tests after owner foundation changes, including new owner context/query/write-guard/route-binding contract coverage.
2. Run targeted Signals tests after alert/filter/channel/idempotency/backfill/integration changes.
3. Run targeted Filament Signals tests after resource/UI updates.
4. Run targeted Cart tests for storage, condition owner scoping, performance indexes, and config table-name cleanup.
5. Run targeted Filament Cart tests for snapshots, synchronization, cart actions, condition actions, abandonment marker, and operational events.
6. Run PHPStan per touched package only: commerce-support, signals, filament-signals, cart, and filament-cart.
7. Run grep checks for removed symbols: `RecoveryAttempt`, `RecoveryCampaign`, `RecoveryTemplate`, `CartDailyMetrics`, `AlertRule`, `AlertLog`, `segment_key`, and public `owner_key` should not remain in cart/filament-cart except in intentional docs/migration instructions.
8. Run owner-scope grep checks for `DB::table`, `withoutOwnerScope`, `getEloquentQuery`, route params, aggregates, and explicit owner opt-outs in touched packages.
9. Verify no forbidden DB constraints/cascades were added in migrations.
10. Verify docs no longer advertise cart-local recovery, metrics, or alert rule management.

**Decisions**

- Signals owns optional cart intelligence; cart and filament-cart never call Signals directly.
- Signals integrations are explicit opt-in even when Signals is installed.
- Filament-cart emits operational events; Signals listens and handles metrics/alerts when enabled.
- Filament-signals owns analytics and alert rule UI.
- Cart-local recovery, metrics, and alerts are deleted outright after Signals replacement capability exists.
- Owner foundation is implemented first.
- Owner config standardizes to top-level `owner` sections.
- Explicit global context is required for global access in owner mode.
- Hidden owner-scope uniqueness keys are internal only and live in commerce-support.
- Existing migrations are edited for clean beta schema; manual migration instructions are documented for the one existing deployment.

**Further considerations**

1. Existing uncommitted non-cart owner work should be preserved and should not be mass-reverted or reformatted.
2. Checkout/order completion analytics should come from checkout/orders Signals events, not from a cart `recovered_at` field.
3. The all-tenant-package owner hardening playbook is part of this deliverable, but implementation against those other packages should be separate follow-up work.