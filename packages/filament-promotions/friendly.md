# Filament Promotions friendliness review

This note reviews `packages/filament-promotions` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (1)
- `src/Actions` (2)
- `src/Models` (1 — domain model in Filament package)
- `src/Enums` (1 — enum in Filament package)
- `src/Support` (2)
- `src/Widgets` (2)
- `FilamentPromotionsPlugin.php`
- downstream in `promotions`, `vouchers`, `cart`, `checkout`

## What is already friendly

### Tables and Schemas subfolders

- `PromotionResource/Tables/`, `PromotionResource/Schemas/`

Standard layout for the main resource.

### Single focused resource

- `PromotionResource` (with RM: IssuedVouchers)

The surface is small and focused.

## Findings

### 1. `Models/Promotion.php` is a domain model in the Filament package

**Files**

- `src/Models/Promotion.php`

**Why this hurts friendliness**

The `promotions` domain package owns `Promotion`. Re-declaring in the Filament package is a duplication risk.

**Recommendation**

Use the `promotions` domain model directly. Delete `src/Models/`.

### 2. `Enums/PromotionType.php` is an enum in the Filament package

**Files**

- `src/Enums/PromotionType.php`

**Why this hurts friendliness**

Enums are domain concepts. The `promotions` package should own `PromotionType`.

**Recommendation**

Move to `promotions/Enums/PromotionType.php`.

### 3. `Support/OwnerScopedQueries.php` duplicates the pattern in other Filament packages

**Files**

- `src/Support/OwnerScopedQueries.php`

**Why this hurts friendliness**

`filament-affiliates/Support/OwnerScopedQuery.php`, `filament-vouchers/Support/OwnerScopedQueries.php`, and this file all define the same pattern.

**Recommendation**

Use `commerce-support`'s `OwnerQuery` and `OwnerWriteGuard`. Delete the local helpers.

### 4. `Actions/IssuePromotionVouchersAction.php` is a cross-package action

**Files**

- `src/Actions/IssuePromotionVouchersAction.php`
- `src/Actions/IssuePromotionVouchersFromListAction.php`

**Why this hurts friendliness**

Voucher issuance should live in the `vouchers` package, not in `filament-promotions`. Filament-promotions should be a thin UI layer.

**Recommendation**

Move voucher issuance actions to the `vouchers` package. The Filament package consumes them.

### 5. `Support/PromotionPerformanceInsights.php` is a domain analytics helper

**Files**

- `src/Support/PromotionPerformanceInsights.php`

**Why this hurts friendliness**

Performance insights are domain analytics, not Filament concerns.

**Recommendation**

Move to the `promotions` package or to a `signals`-aware analytics module.

### 6. `PromotionResource` has 3 `getEloquentQuery` references

**Files**

- `PromotionResource` (and possibly parent/trait)

**Why this hurts friendliness**

3 refs to the same method suggest stacked overrides (superclass + trait + class). Each may add its own filter, which makes owner scoping unclear.

**Recommendation**

Audit the call chain. Consolidate into a single `getEloquentQuery` that delegates to `commerce-support`'s `OwnerQuery`.

## Concrete refactor plan

### Phase 1 — strip domain concerns from the Filament package

**Steps**

1. Move `Models/`, `Enums/`, `Support/PromotionPerformanceInsights.php`, and the voucher-issuance Actions to the `promotions`/`vouchers` domain packages.
2. Delete local owner-scope helpers; use `commerce-support`.

### Phase 2 — audit `getEloquentQuery` call chain

**Steps**

1. List all overrides.
2. Consolidate to one.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — strip domain concerns from the Filament package

- [pending] Move `Models/`, `Enums/`, `Support/PromotionPerformanceInsights.php`, and the voucher-issuance Actions to the `promot...
- [pending] Delete local owner-scope helpers; use `commerce-support`.

### Phase 2 — audit `getEloquentQuery` call chain

- [pending] List all overrides.
- [pending] Consolidate to one.



## Suggested verification scope

- Resource tests
- Action tests
- cross-package tests for promotions/vouchers

## Recommended first move

Phase 1 — strip domain concerns from the Filament package. The duplication with other packages and the domain is the most visible structural smell.
