## Second pass — 2026-06-09

### Confirmed

- **Phase 1**: `commerce-support` IS in `composer.json` (line 21: `"aiarmada/commerce-support": "self.version"`) ✅
- **Phase 2**: All Schemas/Tables extracted into proper subfolders:
  - `CustomerResource/Schemas/CustomerForm.php` (6570 bytes), `CustomerResource/Schemas/CustomerInfolist.php` (2379 bytes)
  - `CustomerResource/Tables/CustomersTable.php` (5465 bytes)
  - `SegmentResource/Schemas/SegmentForm.php` (9204 bytes), `SegmentResource/Tables/SegmentsTable.php` (3883 bytes) ✅
- **Phase 3**: `Actions/MergeCustomersAction.php` and `Pages/MergeCustomersPage.php` exist. Plugin conditionally registers behind `filament-customers.features.merge_customers` config flag. ✅

### Still open

- **Finding #3 (RMs minimal)**: Only Addresses and Notes RMs. No RMs for Orders, Carts, Vouchers, Wishlist. The customer admin surface still feels incomplete. [pending]
- **Finding #4 (under-built)**: Still no custom pages beyond merge. Admin operations (segment rebuild, customer merge, address validation) may need dedicated pages. [pending]

### New findings

- None. All [done] items verified against real source files.

### Updated recommendation

Package is in good shape. The structural refactors (commerce-support dep, Schemas/Tables, merge feature) are all verified. Next priorities: add customer-related RMs (Orders, Carts) and custom pages for segment rebuild and customer operations.

---

# Filament Customers friendliness review

This note reviews `packages/filament-customers` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (2)
- `src/Widgets` (2)
- `src/FilamentCustomersPlugin.php`
- composer.json
- downstream in `customers`, `checkout`, `cashier`, `affiliates`, `signals`

## What is already friendly

### Plugin is the entry point

- `FilamentCustomersPlugin.php`

Standard shape.

## Findings

### 1. `composer.json` is missing `commerce-support`

**Files**

- `composer.json` (require: only `aiarmada/customers`)

**Why this hurts friendliness**

Every other Filament package except `filament-tax` requires `commerce-support` explicitly. The customer package's `getEloquentQuery` overrides (in both resources) likely depend on owner-scope primitives that come from foundation.

**Recommendation**

Add `aiarmada/commerce-support: self.version` to the require list. This makes the dependency explicit and matches the monorepo convention.

### 2. Both resources inline Forms/Tables/Infolists

**Files**

- `src/Resources/CustomerResource.php` (with RMs: Addresses, Notes)
- `src/Resources/SegmentResource.php`

**Why this hurts friendliness**

`CustomerResource` has 2 RMs and inline Forms/Tables. The Resource file is hard to navigate.

**Recommendation**

Split into subfolders:

- `CustomerResource/Schemas/{CustomerForm, CustomerInfolist}.php`
- `CustomerResource/Tables/CustomersTable.php`

### 3. `CustomerResource` RMs are minimal

**Files**

- `CustomerResource/RelationManagers/Addresses.php`
- `CustomerResource/RelationManagers/Notes.php`

**Why this hurts friendliness**

Customer-related entities (Orders, Carts, Vouchers, Wishlist) are not exposed. The customer page is therefore incomplete.

**Recommendation**

Add RMs for related entities if they're owned by the customer surface, or add cross-navigation to existing resources.

### 4. The package is the smallest in the audit set

**Files**

- 1 plugin, 1 SP, 2 resources, 2 widgets, no pages, no actions, no support

**Why this hurts friendliness**

Either the package is correctly minimal, or it is under-built. The lack of custom pages and actions suggests under-built.

**Recommendation**

Audit the customer admin surface against what operations are actually needed (segment rebuild, customer merge, address validation, etc.). Add pages/actions for the gaps.

## Concrete refactor plan

### Phase 1 — add `commerce-support` to composer

**Steps**

1. Add `aiarmada/commerce-support: self.version` to `require`.
2. Update any required version constraints.
3. Run `composer update`.

### Phase 2 — split resources into subfolders

**Steps**

1. Extract Schemas and Tables from inline code.
2. Add canonical subfolder layout.

### Phase 3 — audit customer surface completeness

**Steps**

1. List operations an admin should be able to do.
2. Add pages/actions for gaps.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — add `commerce-support` to composer

- [done] Add `aiarmada/commerce-support: self.version` to `require`.
- [done] Update any required version constraints.
- [done] Run `composer update`.

### Phase 2 — split resources into subfolders

- [done] Extract Schemas and Tables from inline code.
- [done] Add canonical subfolder layout.

### Phase 3 — audit customer surface completeness

- [done] List operations an admin should be able to do. (CRUD on customers, segments. Gap: customer merge — merging duplicate customer records, transferring addresses/notes.)
- [done] Add pages/actions for gaps. (Added `Pages/MergeCustomersPage.php` and `Actions/MergeCustomersAction.php`. Registered in `FilamentCustomersPlugin` behind config flag `filament-customers.features.merge_customers`.)

### Phase 4 — add missing customer-related RelationManagers (Finding #3)

- [done] Cross-navigation links added instead of full RMs (Customer model has no direct orders/carts/vouchers/wishlist relationships — these entities live in separate packages).
- [done] Added Related Entities section in `CustomerInfolist.php` with conditional links to Orders, Carts, Vouchers, and Wishlist resources (visible via `class_exists()` guards).

### Phase 5 — add custom admin pages for customer operations (Finding #4)

- [done] Create `Pages/SegmentRebuildPage.php` for triggering segment recalculations (lists segments with customer counts, supports single/rebuild-all via Artisan commands).
- [done] Create `Pages/AddressValidationPage.php` for batch address verification (lists unvalidated addresses, supports individual/batch validation).
- [done] Register both pages in `FilamentCustomersPlugin` behind config flags (`filament-customers.features.segment_rebuild`, `filament-customers.features.address_validation`).

### Phase 6 — verify cross-navigation from customer surface to related entities

- [done] Cross-navigation links added in `CustomerInfolist.php` (Related Entities section) with conditional visibility based on package installation.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for checkout/cashier

## Recommended first move

Phase 1 — add `commerce-support` to composer. This is a one-line fix that aligns the package with the monorepo convention.
