# Filament Vouchers friendliness review

This note reviews `packages/filament-vouchers` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (3)
- `src/Pages` (2)
- `src/Widgets` (9)
- `src/Actions` (6)
- `src/Exports` (1)
- `src/Extensions` (1)
- `src/Services` (1)
- `src/Support` (4)
- `src/Models` (1 — domain model in Filament package)
- `FilamentVouchersPlugin.php`
- downstream in `vouchers`, `cart`, `affiliates`, `checkout`, `signals`

## What is already friendly

### Tables and Schemas subfolders

- `VoucherResource/Tables/`, `VoucherResource/Schemas/`
- 2 tables, 2 schemas

Standard layout for the main resource.

### Plugin is the entry point

- `FilamentVouchersPlugin.php`

## Findings

### 1. `Models/Voucher.php` is a domain model in the Filament package

**Files**

- `src/Models/Voucher.php`

**Why this hurts friendliness**

The `vouchers` domain package owns the `Voucher` model. Re-declaring it in the Filament package is a duplication risk.

**Recommendation**

Use the `vouchers` domain model directly. Delete `src/Models/`.

### 2. `Support/OwnerScopedQueries.php` and `Support/Integrations/FilamentCartBridge` overlap with other packages

**Files**

- `src/Support/OwnerScopedQueries.php` (same pattern as `filament-affiliates/Support/OwnerScopedQuery.php`)
- `src/Support/Integrations/FilamentCartBridge` (similar to `filament-affiliates/Support/Integrations/CartBridge.php` and `filament-shipping/Services/CartBridge.php`)

**Why this hurts friendliness**

Three different Filament packages each define their own owner-scope helper and cart bridge. This is duplicated orchestration.

**Recommendation**

Use `commerce-support`'s `OwnerQuery` and `OwnerWriteGuard`. Move cart bridges to the `vouchers`/`cart` domain packages.

### 3. 9 widgets likely overlap

**Files**

- `AppliedVoucherBadgesWidget`
- `AppliedVouchersWidget`
- `QuickApplyVoucherWidget`
- `RedemptionTrendChart`
- `VoucherCartStatsWidget`
- `VoucherStatsWidget`
- `VoucherSuggestionsWidget`
- `VoucherUsageTimelineWidget`
- `VoucherWalletStatsWidget`

**Why this hurts friendliness**

9 widgets is a lot. `AppliedVoucherBadgesWidget` and `AppliedVouchersWidget` look like duplicates; `VoucherCartStatsWidget` and `VoucherStatsWidget` look like duplicates.

**Recommendation**

Audit the 9 widgets. Collapse near-duplicates. Move any domain logic to a `Support/VoucherStatsAggregator.php` service.

### 4. `Pages/StackingConfigurationPage.php` and `Pages/TargetingConfigurationPage.php` are settings-as-Page

**Files**

- `src/Pages/StackingConfigurationPage.php`
- `src/Pages/TargetingConfigurationPage.php`

**Why this hurts friendliness**

Settings belong in the `vouchers` package's `Settings/` or as a `ManageXSettings` Filament page that updates `vouchers` settings.

**Recommendation**

If these are settings UIs, move to `vouchers` settings or use Filament's settings page pattern. If they're domain configuration, keep but document.

### 5. `Extensions/CartVoucherActions.php` extends cart from a Filament package

**Files**

- `src/Extensions/CartVoucherActions.php`

**Why this hurts friendliness**

A Filament package should not own a cart extension. The extension belongs in the `vouchers` package.

**Recommendation**

Move to `vouchers/Support/Extensions/CartVoucherActions.php` or similar.

### 6. `Exports/VoucherUsageExporter.php` is a Filament export

**Files**

- `src/Exports/VoucherUsageExporter.php`

**Why this hurts friendliness**

Exports are domain concerns. Belong in the `vouchers` package's `Exports/`.

**Recommendation**

Move to `vouchers/Exports/VoucherUsageExporter.php`.

## Concrete refactor plan

### Phase 1 — strip domain concerns from the Filament package

**Steps**

1. Move `Models/`, `Extensions/`, `Exports/`, and `Support/Integrations/` to the `vouchers` domain package.
2. Delete local owner-scope helpers; use `commerce-support`.
3. Re-import in the Filament package.

### Phase 2 — collapse duplicate widgets

**Steps**

1. Audit the 9 widgets.
2. Collapse near-duplicates.
3. Move aggregations to a `Support/VoucherStatsAggregator.php` service.

### Phase 3 — decide on settings pages

**Steps**

1. Audit `StackingConfigurationPage` and `TargetingConfigurationPage`.
2. Move to settings or keep with documentation.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — strip domain concerns from the Filament package

- [pending] Move `Models/`, `Extensions/`, `Exports/`, and `Support/Integrations/` to the `vouchers` domain package.
- [pending] Delete local owner-scope helpers; use `commerce-support`.
- [pending] Re-import in the Filament package.

### Phase 2 — collapse duplicate widgets

- [pending] Audit the 9 widgets.
- [pending] Collapse near-duplicates.
- [pending] Move aggregations to a `Support/VoucherStatsAggregator.php` service.

### Phase 3 — decide on settings pages

- [pending] Audit `StackingConfigurationPage` and `TargetingConfigurationPage`.
- [pending] Move to settings or keep with documentation.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- Widget tests
- cross-package tests for vouchers/cart/affiliates/checkout

## Recommended first move

Phase 1 — strip domain concerns from the Filament package. The duplication with other packages and with the `vouchers` domain is the most visible structural smell.
