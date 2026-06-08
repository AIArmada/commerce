# Filament Cashier friendliness review

This note reviews `packages/filament-cashier` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (2)
- `src/Pages` (3 admin, 4 customer)
- `src/Widgets` (5 admin, 3 customer)
- `src/Models` (2 — domain models in Filament package)
- `src/Support` (7)
- `src/Policies`
- `src/Components`
- `src/CustomerPortal/`
- `FilamentCashierPlugin.php` (has `customerPortalMode()` toggle)
- downstream in `cashier`, `customers`, `checkout`

## What is already friendly

### Customer Portal is separated

- `src/CustomerPortal/` is its own panel subpackage
- `BillingPanelProvider.php` (in CustomerPortal)

The portal/admin split is real. The plugin's `customerPortalMode()` toggle is a good pattern.

### Plugin gates features

- `FilamentCashierPlugin.php`

Standard entry point.

## Findings

### 1. `Models/UnifiedInvoiceRecord.php` and `Models/UnifiedSubscriptionRecord.php` are domain models in the Filament package

**Files**

- `src/Models/UnifiedInvoiceRecord.php`
- `src/Models/UnifiedSubscriptionRecord.php`

**Why this hurts friendliness**

The `cashier` domain owns the invoice/subscription data. The Filament package re-declares them.

**Recommendation**

Use the `cashier` domain models directly. Delete `src/Models/`. If `Unified*Record` is a true abstraction, document it as a contract and have the cashier domain implement it.

### 2. `Support/UnifiedInvoice.php` and `Support/UnifiedSubscription.php` mirror the model names

**Files**

- `src/Support/UnifiedInvoice.php`
- `src/Support/UnifiedSubscription.php`

**Why this hurts friendliness**

Two support classes that mirror the two model names. They look like DTOs/shims wrapping the cashier core. The boundary between the support class and the model is unclear.

**Recommendation**

Audit both. Make one the canonical entry point. Document the relationship.

### 3. `Support/CashierOwnerScope.php` is a local owner-scope helper

**Files**

- `src/Support/CashierOwnerScope.php`

**Why this hurts friendliness**

`commerce-support` provides owner-scope primitives. The local helper duplicates the pattern.

**Recommendation**

Replace with `commerce-support`'s `OwnerScope` and `OwnerQuery`. Delete the local helper.

### 4. `Support/GatewayDetector.php`, `Support/InvoiceStatus.php`, `Support/SubscriptionStatus.php`, `Support/CurrencyFormatter.php` are domain concerns

**Files**

- `src/Support/GatewayDetector.php`
- `src/Support/InvoiceStatus.php`
- `src/Support/SubscriptionStatus.php`
- `src/Support/CurrencyFormatter.php`

**Why this hurts friendliness**

Gateway detection, status mapping, and currency formatting are domain concerns. Belong in the `cashier` package.

**Recommendation**

Move to the `cashier` package. The Filament package consumes them.

### 5. No `Schemas/` or `Tables/` subfolders in any resource

**Files**

- Both `UnifiedInvoiceResource` and `UnifiedSubscriptionResource` are minimal `Pages` only with inline Forms/Tables.

**Why this hurts friendliness**

The standard layout is missing. Resource files are monolithic.

**Recommendation**

Split into subfolders following the standard pattern.

### 6. 8 widgets (5 admin + 3 customer) likely overlap with `filament-cashier-chip`

**Files**

- `Widgets/GatewayBreakdownWidget`
- `Widgets/GatewayComparisonWidget`
- `Widgets/TotalMrrWidget`
- `Widgets/TotalSubscribersWidget`
- `Widgets/UnifiedChurnWidget`
- `CustomerPortal/Widgets/...` (3)

**Why this hurts friendliness**

`filament-cashier-chip` has 7 widgets, several with similar names (RevenueChartWidget, MRRWidget, ChurnRateWidget). Duplicate surfaces for similar metrics.

**Recommendation**

Audit the two packages' widgets. Pick one canonical per metric. The cashier package should own generic metrics; cashier-chip should own CHIP-specific ones.

### 7. `Pages/ManageSubscriptions.php` (CustomerPortal) has 4 query calls

**Files**

- `CustomerPortal/Pages/ManageSubscriptions.php`

**Why this hurts friendliness**

Customer-facing read path with inline queries. May bypass owner scoping.

**Recommendation**

Move queries to a `Support/CustomerSubscriptionsQuery.php` helper. Use `OwnerQuery` to ensure scope.

## Concrete refactor plan

### Phase 1 — strip domain concerns from the Filament package

**Steps**

1. Move `Models/`, `Support/`, and the support classes to the `cashier` package.
2. Delete local owner-scope helpers; use `commerce-support`.

### Phase 2 — split resources into subfolders

**Steps**

1. Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — audit widget overlap with `filament-cashier-chip`

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

### Phase 1 — strip domain concerns from the Filament package

- [pending] Move `Models/`, `Support/`, and the support classes to the `cashier` package.
- [pending] Delete local owner-scope helpers; use `commerce-support`.

### Phase 2 — split resources into subfolders

- [pending] Add `Schemas/` and `Tables/` to both resources.

### Phase 3 — audit widget overlap with `filament-cashier-chip`

- [pending] List widgets in both packages.
- [pending] Pick canonical per metric.



## Suggested verification scope

- per-Resource tests
- Widget tests (admin and customer)
- cross-package tests for cashier/customers/checkout

## Recommended first move

Phase 1 — strip domain concerns from the Filament package. The duplication with `cashier` is the most visible structural smell.
