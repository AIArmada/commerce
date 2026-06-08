# Filament Signals friendliness review

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

- [pending] Extract a generic `ReportPage` base.
- [pending] Refactor the 9 report pages.
- [pending] Or, move report definitions to config and have a single page render them.

### Phase 2 — split resources into subfolders

- [pending] Add `Schemas/` and `Tables/` to all 7 resources.

### Phase 3 — consolidate `getEloquentQuery` overrides

- [pending] Audit the call chain.
- [pending] Consolidate.



## Suggested verification scope

- per-Resource tests
- per-Page tests
- Widget tests
- cross-package tests for signals/affiliates/cart/checkout/vouchers/orders

## Recommended first move

Phase 1 — generalize report pages. The 9 hand-rolled report pages are the most visible structural smell.
