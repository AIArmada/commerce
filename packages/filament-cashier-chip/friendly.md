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

- [pending] Replace `Support/CashierChipOwnerScope.php` with `commerce-support`'s `OwnerScope`.
- [pending] Update `BaseCashierChipResource` to delegate to `commerce-support`.

### Phase 2 — strip domain concerns

- [pending] Move `Support/FormatsSubscriptionStatus.php` to `cashier-chip`.

### Phase 3 — adopt `CustomerPortal/` convention

- [pending] Move customer-facing pages to `CustomerPortal/`.
- [pending] Update panel provider.

### Phase 4 — audit widget overlap with `filament-cashier`

- [pending] List widgets in both packages.
- [pending] Pick canonical per metric.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for cashier-chip/cashier/chip

## Recommended first move

Phase 1 — adopt `commerce-support` owner-scope primitives. This is a one-file fix that aligns the package with the monorepo convention.
