## Second pass — 2026-06-09

### Confirmed

- **Phase 2**: `Settings/` moved to `cart` package. `src/Settings/` directory deleted. ✅
- **Phase 3**: `ConditionResource` uses `cart`'s `Condition` model directly. ✅
- **Phase 4**: Widgets collapsed from 5 to 3: `AbandonedCartsWidget.php`, `CartStatsWidget.php`, `RecentActivityWidget.php`. All registered conditionally behind config flags in `FilamentCartPlugin.php`. ✅
- **Phase 2 [note]**: Events/Listeners/Jobs/Commands/Services kept. Documented reason: they bridge domain events to snapshot storage. ✅

### Still open

- **Finding #7 — `withoutOwnerScope` bypasses**: `CartConditionBatchRemoval.php:210` and `MarkAbandonedCartsCommand.php:118` still use raw `->withoutOwnerScope()`. `OwnerContext` guards wrap the call sites, but the recommendation to use `OwnerQuery` or `OwnerContext::withOwner(null, ...)` for the query itself was not implemented. [pending]

### New findings

- **N1 — `CartConditionResource` is dead code**: `CartConditionResource/` exists on disk with live `Schemas/CartConditionForm.php` and `Tables/CartConditionsTable.php`, but is NOT registered in `FilamentCartPlugin.php` (only `ConditionResource` is registered). The old version was correctly moved to `_archive/`, but a newer `CartConditionResource` with dated files (May/Dec 2025) remains as an orphaned, unreachable resource. It should be deleted or moved to `_archive/`.
- **N2 — `ConditionResource` has full subfolders: The canonical `ConditionResource` has `Pages/` (CreateCondition, EditCondition, ListConditions), `Schemas/ConditionForm.php`, `Tables/ConditionsTable.php` — a proper full layout.

### Updated recommendation

Delete the orphaned `CartConditionResource/` directory (move to `_archive/` if preservation is desired). Replace raw `->withoutOwnerScope()` calls with `OwnerQuery` or `OwnerContext::withOwner(null, fn () => ...)` in `CartConditionBatchRemoval.php` and `MarkAbandonedCartsCommand.php`.

---

# Filament Cart friendliness review

This note reviews `packages/filament-cart` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (7)
- `src/Pages` (2)
- `src/Widgets` (5)
- `src/Services` (7)
- `src/Actions` (4)
- `src/Models` (4)
- `src/Listeners` (3)
- `src/Events` (4)
- `src/Commands` (1)
- `src/Jobs` (1)
- `src/Settings`
- downstream in `cart`, `signals`, `filament-events`, `filament-signals`

## What is already friendly

### Tables and Schemas subfolders

4 resources have proper `Schemas/` and `Tables/` subfolders. The structural pattern is right for the core resources.

### Plugin gates admin surface

- `FilamentCartPlugin.php`

The plugin is the entry point for conditional registration.

## Findings

### 1. `CartConditionResource` and `ConditionResource` are duplicates

**Files**

- `src/Resources/CartConditionResource/{Pages, Tables, Schemas}/`
- `src/Resources/ConditionResource/{Pages, Tables, Schemas}/`

**Why this hurts friendliness**

Two parallel resources for the same model. The Schemas and Tables are near-identical. Callers and tests have to know which one to use.

**Recommendation**

Pick one canonical resource. Delete or alias the other. This is the most visible architectural smell in the package.

### 2. Filament package redefines domain models

**Files**

- `src/Models/Cart.php`
- `src/Models/CartCondition.php`
- `src/Models/CartItem.php`
- `src/Models/Condition.php`

**Why this hurts friendliness**

The `cart` domain package owns these models. The Filament package re-declares them. Schema changes require editing both packages, and behavior may drift.

**Recommendation**

Re-export the domain models from the Filament package, or use the domain models directly. Do not maintain parallel models.

### 3. Domain-level orchestration lives in the Filament package

**Files**

- `src/Events/` (4 events)
- `src/Listeners/` (3 listeners)
- `src/Jobs/SyncNormalizedCartJob.php`
- `src/Commands/MarkAbandonedCartsCommand.php`

**Why this hurts friendliness**

The cart domain package owns the cart lifecycle. Filament is a UI layer. Domain-level orchestration (events, listeners, jobs, commands) should not live in `filament-cart`.

**Recommendation**

Move these to the `cart` domain package. The Filament package should only own Resources, Pages, Widgets, and Schema/Table classes.

### 4. Services count is high (7) for a UI package

**Files**

- `Services/NormalizedCartSynchronizer.php`
- `Services/CartConditionBatchRemoval.php` (uses `withoutOwnerScope`)
- `Services/OwnerActionGuard.php`
- `Services/CartSyncManager.php`
- `Services/CartInstanceManager.php`
- `Services/...`

**Why this hurts friendliness**

Filament packages should be thin UI. Cart-domain services belong in the `cart` package.

**Recommendation**

Move all services to the `cart` domain package. The Filament package should consume the domain services, not own them.

### 5. Three "stats" widgets likely overlap

**Files**

- `Widgets/CartStatsWidget.php`
- `Widgets/CartStatsOverviewWidget.php`
- `Widgets/LiveStatsWidget.php`

**Why this hurts friendliness**

Three different views of the same metric. They may compute slightly different numbers and confuse the user.

**Recommendation**

Collapse into one canonical `CartStatsWidget`. Move any genuinely different views (live vs historical) into a single widget with state.

### 6. `Settings/` should not live in a Filament package

**Files**

- `src/Settings/`

**Why this hurts friendliness**

Settings are domain config. They belong in the `cart` package.

**Recommendation**

Move to the `cart` domain package.

### 7. `withoutOwnerScope` use is explicit but not justified

**Files**

- `src/Services/CartConditionBatchRemoval.php`
- `src/Commands/MarkAbandonedCartsCommand.php`

**Why this hurts friendliness**

These bypasses are likely needed (batch operations are cross-tenant), but they should use `commerce-support`'s `OwnerQuery` or be wrapped in `OwnerContext` with explicit opt-out documentation.

**Recommendation**

Use `commerce-support`'s owner-batch helper or `OwnerContext::withOwner(null, ...)` with a comment explaining the cross-tenant intent.

## Concrete refactor plan

### Phase 1 — collapse the duplicate Condition resources

**Steps**

1. Pick `CartConditionResource` or `ConditionResource` as canonical.
2. Move the other to `Resources/_archive/` or delete.
3. Update navigation.

### Phase 2 — strip domain orchestration from the Filament package

**Steps**

1. Move `Events/`, `Listeners/`, `Jobs/`, `Commands/`, `Settings/`, and `Services/` to the `cart` package.
2. Re-import in the Filament package.
3. Update tests.

### Phase 3 — replace local models with domain models

**Steps**

1. Use `cart`'s `Cart`, `CartItem`, `CartCondition`, `Condition` directly.
2. Delete `src/Models/`.
3. Update Resource references.

### Phase 4 — collapse stats widgets

**Steps**

1. Pick one canonical `CartStatsWidget`.
2. Merge the other two into it as state.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — collapse the duplicate Condition resources

- [done] Pick `CartConditionResource` or `ConditionResource` as canonical.
- [done] Move the other to `Resources/_archive/` or delete.
- [done] Update navigation.

### Phase 2 — strip domain orchestration from the Filament package

- [done] Move `Settings/` to the `cart` package.
- [done] Re-import in the Filament package.
- [done] Update tests.
- [note] Events/Listeners/Jobs/Commands/Services are intrinsically snapshot-specific and cannot be moved to the domain package without introducing a circular dependency (they bridge domain cart events to snapshot storage). They remain in filament-cart as a legitimate UI-layer concern.

### Phase 3 — replace local models with domain models

- [done] `ConditionResource` already uses `cart`'s `Condition` model directly.
- [done] `Cart` model is a snapshot model on `cart_snapshots` table, distinct from `Cart\Models\CartModel` (operational cart). Both are legitimate models serving different purposes.
- [done] Updated Resource references where applicable.
- [note] `CartCondition` and `CartItem` are snapshot-condition/snapshot-item models on separate tables. Not duplicates of domain models.

### Phase 4 — collapse stats widgets

- [done] Pick one canonical `CartStatsWidget`.
- [done] Merge the other two (`CartStatsOverviewWidget`, `LiveStatsWidget`) into it as state.

### Phase 5 — delete orphaned `CartConditionResource` (Finding N1)

- [done] Verify `CartConditionResource` is not reachable via routing, navigation, or plugin registration (confirmed — only `ConditionResource` is registered in `FilamentCartPlugin`).
- [done] Move `CartConditionResource/` to `Resources/_archive/CartConditionResource/`.
- [done] Verify `ConditionResource` (the canonical one) is registered and fully functional.

### Phase 6 — replace raw `withoutOwnerScope` bypasses (Finding #7)

- [done] Replace `->withoutOwnerScope()` in `CartConditionBatchRemoval.php:210` with `OwnerContext::withOwner(null, fn () => CartModel::query()->forOwner(null, includeGlobal: true))`.
- [done] Replace `->withoutOwnerScope()` in `MarkAbandonedCartsCommand.php:118` with `OwnerContext::withOwner(null, fn () => Cart::query()->...)`.
- [done] Cross-tenant intent is explicit via `OwnerContext::withOwner(null, ...)`.



## Suggested verification scope

- per-Resource tests
- Widget tests
- `cart` package tests after the move
- cross-package tests for signals/filament-signals

## Recommended first move

Phase 1 — collapse the duplicate Condition resources. This is the most visible smell and the cleanup is mechanical.
