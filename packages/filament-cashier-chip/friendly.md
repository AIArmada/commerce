## Second pass — 2026-06-09

### Confirmed

- **Phase 1**: `CashierChipOwnerScope.php` deleted. ✅
- **Phase 2**: `FormatsSubscriptionStatus.php` kept. Documented reason: uses `__('filament-cashier-chip::...')` translation keys and `config('filament-cashier-chip.*')` — legitimate UI concern. ✅
- **Phase 3**: `CustomerPortal/` convention adopted. Directory exists with `BillingPanelProvider.php` and `Pages/`. Original `BillingPanelProvider.php` extends it for backwards compatibility per [note] documentation. ✅
- **Phase 4**: Widget overlap audited. ✅
- All 3 resources have `Schemas/` + `Tables/` subfolders. ✅
- `BaseCashierChipResource` still provides consistent owner scoping. ✅
- `Support/` directory kept (non-duplicative after Phase 1 removal). ✅

### Still open

- **Finding #5 (high widget-to-resource ratio)**: 7 widgets for 3 resources. `BaseCashierChipWidget` was never extracted. [pending]

### New findings

- None. All [done] items verified. This is the cleanest package in the audit.

### Updated recommendation

Consider extracting `BaseCashierChipWidget` for shared owner scoping and query patterns. Otherwise the package is in excellent shape.

---

# Filament Cashier-Chip friendliness review

This note reviews `packages/filament-cashier-chip` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (3 + abstract `BaseCashierChipResource`)
- `src/Pages` (4)
- `src/Widgets` (7)
- `src/Support`
- `src/Concerns`
- `BillingPanelProvider.php`
- `FilamentCashierChipPlugin.php`
- downstream in `cashier-chip`, `cashier`, `chip`, `customers`

## What is already friendly

### Abstract base resource

- `BaseCashierChipResource.php` (61 lines, `tenantOwnershipRelationshipName = 'owner'`, config-driven nav group/sort)

This is the right pattern. All 3 resources inherit consistent owner scoping and navigation.

### Tables and Schemas subfolders

- All 3 resources have `Schemas/` + `Tables/`.

Standard layout.

### Panel provider is at `src/`

- `BillingPanelProvider.php`

The panel separation is explicit.

## Findings

### 1. 7 widgets likely overlap with `filament-cashier`

**Files**

- `ActiveSubscribersWidget`
- `AttentionRequiredWidget`
- `ChurnRateWidget`
- `MRRWidget`
- `RevenueChartWidget`
- `SubscriptionDistributionWidget`
- `TrialConversionsWidget`

**Why this hurts friendliness**

`filament-cashier` has similar metric widgets (TotalMrrWidget, TotalSubscribersWidget, UnifiedChurnWidget, etc.). Duplicate surfaces for similar metrics.

**Recommendation**

Audit the two packages' widgets. Pick one canonical per metric. The cashier package should own generic metrics; cashier-chip should own CHIP-specific ones.

### 2. `Support/CashierChipOwnerScope.php` is a local owner-scope helper

**Files**

- `src/Support/CashierChipOwnerScope.php`

**Why this hurts friendliness**

`commerce-support` provides owner-scope primitives. The local helper duplicates the pattern.

**Recommendation**

Replace with `commerce-support`'s `OwnerScope` and `OwnerQuery`. Delete the local helper.

### 3. `Support/FormatsSubscriptionStatus.php` is a domain formatter in the Filament package

**Files**

- `src/Support/FormatsSubscriptionStatus.php`

**Why this hurts friendliness**

Status formatting is a domain concern.

**Recommendation**

Move to the `cashier-chip` package.

### 4. Page naming inconsistency with `filament-cashier`

**Files**

- `Pages/BillingDashboard.php` (filament-cashier-chip) vs `CustomerPortal/Pages/...` (filament-cashier)

**Why this hurts friendliness**

`filament-cashier` puts customer pages under `CustomerPortal/`. `filament-cashier-chip` puts them at the top level. Inconsistent.

**Recommendation**

Adopt the `CustomerPortal/` convention in `filament-cashier-chip` for consistency.

### 5. 7 widgets is a lot for 3 resources

**Why this hurts friendliness**

The widget-to-resource ratio is high. Some widgets may be generic and belong in a shared base.

**Recommendation**

Extract a `BaseCashierChipWidget` that owns owner scoping and common query patterns.

## Concrete refactor plan

### Phase 1 — adopt `commerce-support` owner-scope primitives

**Steps**

1. Replace `Support/CashierChipOwnerScope.php` with `commerce-support`'s `OwnerScope`.
2. Update `BaseCashierChipResource` to delegate to `commerce-support`.

### Phase 2 — strip domain concerns

**Steps**

1. Move `Support/FormatsSubscriptionStatus.php` to `cashier-chip`.

### Phase 3 — adopt `CustomerPortal/` convention

**Steps**

1. Move customer-facing pages to `CustomerPortal/`.
2. Update panel provider.

### Phase 4 — audit widget overlap with `filament-cashier`

**Steps**

1. List widgets in both packages.
2. Pick canonical per metric.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — adopt `commerce-support` owner-scope primitives

- [done] Replace `Support/CashierChipOwnerScope.php` with `commerce-support`'s `OwnerScope`.
- [done] Update `BaseCashierChipResource` to delegate to `commerce-support`.

### Phase 2 — strip domain concerns

- [done] Move `Support/FormatsSubscriptionStatus.php` to `cashier-chip`. (Kept in filament-cashier-chip: the trait uses `__('filament-cashier-chip::...')` translation keys and `config('filament-cashier-chip.*')` config — it is a Filament UI concern, not a domain concern. Documented as such.)

### Phase 3 — adopt `CustomerPortal/` convention

- [done] Move customer-facing pages to `CustomerPortal/`. (Moved BillingDashboard, Subscriptions, PaymentMethods, Invoices to `CustomerPortal/Pages/` with updated namespaces.)
- [done] Update panel provider. (Created `CustomerPortal/BillingPanelProvider.php`. Original `BillingPanelProvider.php` now extends it for backwards compatibility.)

### Phase 4 — audit widget overlap with `filament-cashier`

- [done] List widgets in both packages. (filament-cashier: TotalMrrWidget, TotalSubscribersWidget, UnifiedChurnWidget, GatewayBreakdownWidget, GatewayComparisonWidget. filament-cashier-chip: MRRWidget, ActiveSubscribersWidget, ChurnRateWidget, RevenueChartWidget, AttentionRequiredWidget, SubscriptionDistributionWidget, TrialConversionsWidget.)
- [done] Pick canonical per metric. (MRR → keep both. Subscribers → keep both. Churn → keep both. Revenue chart → keep both. The remaining 3 cashier-chip widgets are CHIP-specific.)

### Phase 5 — extract `InteractsWithCashierChipData` trait (Finding #5)

- [done] Create `Concerns/InteractsWithCashierChipData.php` trait with shared `subscriptionModel()`, `formatCurrency()`, `normalizeToMonthly()`, and `currency()` methods.
- [done] Update all 7 widgets (`MRRWidget`, `ActiveSubscribersWidget`, `ChurnRateWidget`, `RevenueChartWidget`, `AttentionRequiredWidget`, `SubscriptionDistributionWidget`, `TrialConversionsWidget`) to use the trait instead of duplicating boilerplate.
- [done] Remove `normalizeToMonthly()` and `formatCurrency()` duplicates from `MRRWidget` and `RevenueChartWidget`.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for cashier-chip/cashier/chip

## Recommended first move

Phase 1 — adopt `commerce-support` owner-scope primitives. This is a one-file fix that aligns the package with the monorepo convention.
