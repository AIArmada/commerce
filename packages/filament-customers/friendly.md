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

- [pending] Add `aiarmada/commerce-support: self.version` to `require`.
- [pending] Update any required version constraints.
- [pending] Run `composer update`.

### Phase 2 — split resources into subfolders

- [pending] Extract Schemas and Tables from inline code.
- [pending] Add canonical subfolder layout.

### Phase 3 — audit customer surface completeness

- [pending] List operations an admin should be able to do.
- [pending] Add pages/actions for gaps.



## Suggested verification scope

- per-Resource tests
- Widget tests
- cross-package tests for checkout/cashier

## Recommended first move

Phase 1 — add `commerce-support` to composer. This is a one-line fix that aligns the package with the monorepo convention.
