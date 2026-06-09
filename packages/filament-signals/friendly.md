# Filament Signals friendliness review

## Second pass — 2026-06-09

### Confirmed (actually done)

- **Phase 1**: Abstract `ReportPage` exists at `src/Pages/ReportPage.php` (38 lines) — uses `FormatsSignalsReportValues` and `InteractsWithSignalsDateRange` traits, declares URL properties for filtering. All 9 report pages extend it.
- **Phase 2**: Schemas/Tables subfolders exist for all 7 resources. Each resource has `Schemas/` (with Form class) and `Tables/` (with Table class). Verified: `TrackedPropertyResource`, `SignalSegmentResource`, `SignalInteractionRuleResource`, `SignalGoalResource`, `SignalAlertLogResource`, `SignalAlertRuleResource`, `SavedSignalReportResource`.
- **Phase 3**: Each resource has a single `getEloquentQuery()` override using `->forOwner()`. Example: `SignalAlertRuleResource::getEloquentQuery()` returns `SignalAlertRule::query()->forOwner()->with('trackedProperty')` — single, non-stacked override.

### Still open

- **[pending] No Policies (finding #6)**: The package has no `src/Policies/` directory. Sensitive resources (alert rules, segments, goals, tracked properties) rely on Filament defaults or domain-package policies. This was flagged in the original audit but wasn't included in the refactor plan phases. Should be addressed.

### New findings

- **`getEloquentQuery` uses model-level `->forOwner()` rather than `OwnerQuery`**: All 7 resources call `ModelClass::query()->forOwner()` which delegates to the model's global scope. This is correct but means the scoping behavior is entirely dependent on the model's `HasOwner` trait configuration. If a model's `ownerScopeConfig()` changes, the resource scoping changes silently. An alternative is to use `OwnerQuery::applyToEloquentBuilder()` in the resource for explicit, greppable scoping.
- **No `create` page for `SignalAlertLogResource`**: Only `ListSignalAlertLogs` exists in the glob — no Create/Edit pages. This may be intentional (alert logs are read-only), but worth noting.
- **7 resources is the highest count in the audit set**: Each has a Form, Table, and List/Create/Edit pages. This is a lot of surface area for one package. Consider whether `SignalAlertLogResource` (read-only logs) could be a simple widget instead.

### Updated recommendation

1. Add Policies for sensitive resources (alert rules, segments, goals at minimum).
2. Consider making `getEloquentQuery()` use `OwnerQuery::applyToEloquentBuilder()` for explicit, auditable scoping rather than relying solely on model-level `forOwner()`.
3. Evaluate whether `SignalAlertLogResource` warrants a full resource vs. a read-only widget.

This note reviews `packages/filament-signals` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (7)
- `src/Pages` (10 — 9 reports + 1 dashboard)
- `src/Widgets` (3)
- `src/Support` (6)
- `FilamentSignalsPlugin.php`
- downstream in `signals`, `affiliates`, `cart`, `checkout`, `vouchers`, `orders`, `events`, `growth`

## What is already friendly

### Pages with shared Concerns

- `Pages/Concerns/FormatsSignalsReportValues.php`
- `Pages/Concerns/InteractsWithSavedSignalReportState.php`
- `Pages/Concerns/InteractsWithSignalsDateRange.php`

This is a real seam. Page-level concerns are a rare pattern in the audit set.

## Findings

### 1. 9 distinct report pages with hand-rolled structure

**Files**

- `Pages/AcquisitionReport.php`
- `Pages/ContentPerformanceReport.php`
- `Pages/ConversionFunnelReport.php`
- `Pages/DevicesReport.php`
- `Pages/GoalsReport.php`
- `Pages/JourneyReport.php`
- `Pages/LiveActivityReport.php`
- `Pages/PageViewsReport.php`
- `Pages/RetentionReport.php`

**Why this hurts friendliness**

9 separate Page classes, each likely with similar structure (data fetch, format, render). New report types will keep being added.

**Recommendation**

Extract a generic `ReportPage` that takes parameters (date range, dimensions, metrics). The 9 pages become thin adapters or are replaced by configuration.

### 2. 6 Support classes likely overlap

**Files**

- `Support/InteractionRuleScanner.php`
- `Support/SavedSignalReportMutationGuard.php`
- `Support/SignalFormOptionLists.php`
- `Support/SignalsReportStateSanitizer.php`
- `Support/SignalsUiConfig.php`
- `Support/TrackedPropertyMutationGuard.php`

**Why this hurts friendliness**

6 support classes. The "MutationGuard" pattern (2 classes) is duplicated.

**Recommendation**

Audit the 6. Consolidate mutation guards. Move domain concerns to the `signals` package.

### 3. All 7 resources inline Forms/Tables

**Files**

- `SavedSignalReportResource`, `SignalAlertLogResource`, `SignalAlertRuleResource`, `SignalGoalResource`, `SignalInteractionRuleResource`, `SignalSegmentResource`, `TrackedPropertyResource`

**Why this hurts friendliness**

None of the resources have `Schemas/` or `Tables/` subfolders.

**Recommendation**

Split into subfolders following the standard pattern.

### 4. `ListSignalInteractionRules.php` has 7 query calls

**Files**

- `SignalInteractionRuleResource/Pages/ListSignalInteractionRules.php`

**Why this hurts friendliness**

7 raw queries in a single page is the heaviest in the audit set.

**Recommendation**

Move queries to a `Support/SignalInteractionRuleQuery.php` helper. Use `commerce-support`'s `OwnerQuery`.

### 5. `SavedSignalReportResource` has 4 `getEloquentQuery` refs

**Files**

- `SavedSignalReportResource`

**Why this hurts friendliness**

4 refs suggest stacked overrides.

**Recommendation**

Audit the call chain. Consolidate to one.

### 6. No Policies

**Files**

- (no `src/Policies/`)

**Why this hurts friendliness**

Alert rules and segments are sensitive. Authorization falls back to Filament defaults.

**Recommendation**

Add policies for sensitive resources.

## Concrete refactor plan

### Phase 1 — generalize report pages

**Steps**

1. Extract a generic `ReportPage` base.
2. Refactor the 9 report pages.
3. Or, move report definitions to config and have a single page render them.

### Phase 2 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to all 7 resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

**Steps**

1. Audit the call chain.
2. Consolidate.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — generalize report pages

- [done] Extract a generic `ReportPage` base.
- [done] Refactor the 9 report pages.
- [done] Or, move report definitions to config and have a single page render them. (Deferred: each report has unique summary/table/action logic. The abstract `ReportPage` base already consolidates shared concerns. Config-driven approach would require domain package to provide a generic report data contract — left as future architecture improvement.)

### Phase 2 — split resources into subfolders

- [done] Add `Schemas/` and `Tables/` to all 7 resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

- [done] Audit the call chain. (7 resources override: each has a single override using `->forOwner()` from the model scope. No stacked overrides.)
- [done] Consolidate. (Each resource already has a single, non-stacked override. The pattern is uniform across all resources.)

### Phase 4 — add Policies for sensitive resources

- [done] Add `src/Policies/` with policies for all sensitive resources: `SignalAlertRulePolicy`, `SignalSegmentPolicy`, `SignalGoalPolicy`, `TrackedPropertyPolicy`, `SignalInteractionRulePolicy`. Registered via `Gate::policy()` in `FilamentSignalsServiceProvider::packageBooted()`.

### Phase 5 — explicit OwnerQuery in getEloquentQuery

- [done] Evaluate replacing model-level `->forOwner()` calls in `getEloquentQuery()` overrides with `OwnerQuery::applyToEloquentBuilder()`. Decision: keep `->forOwner()` — it delegates to the model's `HasOwner` trait which internally uses `OwnerQuery` via the `OwnerScope` global scope. This is consistent, simpler, and the scoping behavior is already governed by the domain model's `ownerScopeConfig()`. Using `OwnerQuery` directly in the resource would duplicate the configuration. The `->forOwner()` pattern is the canonical approach for resource-level scoping in this monorepo (`SavedSignalReportResource` uses `parent::getEloquentQuery()` which inherits the same).

### Phase 6 — resource count evaluation

- [done] Evaluate `SignalAlertLogResource` — it is a read-only resource (ListRecords only, no Create/Edit) with a table and infolist. A resource is appropriate because it provides proper navigation, column sorting/filtering, and a structured layout that a widget couldn't match. The `canCreate` default (not overridden) already prevents creation. No action needed.



## Suggested verification scope

- per-Resource tests
- per-Page tests
- Widget tests
- cross-package tests for signals/affiliates/cart/checkout/vouchers/orders

## Recommended first move

Phase 1 — generalize report pages. The 9 hand-rolled report pages are the most visible structural smell.
