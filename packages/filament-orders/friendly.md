# Filament Orders friendliness review

This note reviews `packages/filament-orders` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (1)
- `src/Widgets` (4)
- `src/Support`
- `src/Pages` (empty)
- downstream in `orders`, `cashier`, `cashier-chip`, `inventory`, `shipping`, `affiliates`, `signals`

## What is already friendly

### Plugin is the entry point

- `FilamentOrdersPlugin.php`

Standard plugin shape.

### Resource is split into RelationManagers

- `OrderResource` with RMs: Items, Notes, Payments, Refunds

RMs are the right place for related-entity editing on the order surface.

## Findings

### 1. Single Resource for the most-trafficked entity in commerce

**Files**

- `src/Resources/OrderResource.php` (with 4 RMs)

**Why this hurts friendliness**

The `Order` model is the central entity. One resource with everything inline (Forms, Tables, Infolists) is hard to navigate. There is no `Schemas/` or `Tables/` subfolder.

**Recommendation**

Split `OrderResource` into the standard subfolder layout:

- `Schemas/OrderForm.php`, `OrderInfolist.php`
- `Tables/OrdersTable.php`

The main Resource class becomes a thin assembly.

### 2. Empty `Pages/` directory

**Files**

- `src/Pages/` (empty)

**Why this hurts friendliness**

Empty directories are dead code. They imply intent that was never followed.

**Recommendation**

Either populate with real custom pages (OrderTimelinePage, OrderFulfillmentPage) or delete the directory.

### 3. Cross-package data exposure through order RMs

**Files**

- `OrderResource/RelationManagers/Payments.php`
- `OrderResource/RelationManagers/Refunds.php`

**Why this hurts friendliness**

Payments and Refunds are cashier/cashier-chip entities. Showing them as order RMs creates two surfaces for the same data (here and in `filament-cashier`).

**Recommendation**

Either:
- keep the RMs and add navigation links to the cashier resources, or
- drop the RMs and rely on cashier's resource pages

Pick one canonical surface per data.

### 4. Four widgets with no shared base

**Files**

- `Widgets/OrderStatsWidget.php`
- `Widgets/OrderStatusDistributionWidget.php`
- `Widgets/OrderTimelineWidget.php`
- `Widgets/RecentOrdersWidget.php`

**Why this hurts friendliness**

The four widgets are likely written from scratch with similar boilerplate.

**Recommendation**

Extract a `BaseOrderWidget` that handles owner scoping and common query patterns.

### 5. `Support/FilamentOrdersCache.php` is a single-file concern

**Files**

- `src/Support/FilamentOrdersCache.php`

**Why this hurts friendliness**

Cache helpers should live with the data they cache. If only orders uses it, this is fine. If other packages need the same caching pattern, it should move to `commerce-support`.

**Recommendation**

Audit other Filament packages for similar cache helpers. If duplicated, move to foundation.

## Concrete refactor plan

### Phase 1 — split `OrderResource` into subfolders

**Steps**

1. Extract `Schemas/OrderForm.php`, `OrderInfolist.php`.
2. Extract `Tables/OrdersTable.php`.
3. Resource class becomes thin.

### Phase 2 — decide on Payments/Refunds RMs

**Steps**

1. Audit cashier/cashier-chip resources.
2. Pick one canonical surface.
3. Drop the duplicate RMs or add cross-navigation.

### Phase 3 — populate or delete `Pages/`

**Steps**

1. Either add real custom pages, or
2. Delete the directory.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — split `OrderResource` into subfolders

- [pending] Extract `Schemas/OrderForm.php`, `OrderInfolist.php`.
- [pending] Extract `Tables/OrdersTable.php`.
- [pending] Resource class becomes thin.

### Phase 2 — decide on Payments/Refunds RMs

- [pending] Audit cashier/cashier-chip resources.
- [pending] Pick one canonical surface.
- [pending] Drop the duplicate RMs or add cross-navigation.

### Phase 3 — populate or delete `Pages/`

- [pending] Either add real custom pages, or
- [pending] Delete the directory.



## Suggested verification scope

- OrderResource tests
- Widget tests
- cross-package tests for cashier/cashier-chip/inventory/shipping/affiliates

## Recommended first move

Phase 1 — split `OrderResource` into subfolders. The current shape is the most visible structural smell and the cleanup is mechanical.
