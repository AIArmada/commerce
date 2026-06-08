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

- [pending] Pick `CartConditionResource` or `ConditionResource` as canonical.
- [pending] Move the other to `Resources/_archive/` or delete.
- [pending] Update navigation.

### Phase 2 — strip domain orchestration from the Filament package

- [pending] Move `Events/`, `Listeners/`, `Jobs/`, `Commands/`, `Settings/`, and `Services/` to the `cart` package.
- [pending] Re-import in the Filament package.
- [pending] Update tests.

### Phase 3 — replace local models with domain models

- [pending] Use `cart`'s `Cart`, `CartItem`, `CartCondition`, `Condition` directly.
- [pending] Delete `src/Models/`.
- [pending] Update Resource references.

### Phase 4 — collapse stats widgets

- [pending] Pick one canonical `CartStatsWidget`.
- [pending] Merge the other two into it as state.



## Suggested verification scope

- per-Resource tests
- Widget tests
- `cart` package tests after the move
- cross-package tests for signals/filament-signals

## Recommended first move

Phase 1 — collapse the duplicate Condition resources. This is the most visible smell and the cleanup is mechanical.
