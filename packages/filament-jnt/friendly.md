## Second pass — 2026-06-09

### Confirmed

- **Phase 1 — extract shared base Pages**: `ReadOnlyListRecords` and `ReadOnlyViewRecord` exist in `commerce-support/src/Filament/Pages/`. JNT resources import from shared location.
- **Phase 2 — collapse AWB print actions**: Only `PrintAwbTableAction.php` remains. `BulkPrintAwbAction.php` and standalone `PrintAwbAction` removed.
- **Phase 3 — slim down BaseJntResource**: `NavigationBadgeHelper.php` created in `Support/` with cache key generation and nav badge counting. Base resource now 63 lines (down from 89). Uses `OwnerUiScope::apply()`.

### Still open

None — all checklists marked [done].

### New findings

1. **`NavigationBadgeHelper` (and 5 other files) still call `OwnerContext::resolve()` directly.** The refactor replaced the BaseResource's scoping to `OwnerUiScope::apply()`, but `NavigationBadgeHelper.php:22`, `CancelOrderAction.php:117`, `PrintAwbTableAction.php:105,153`, `SyncTrackingAction.php:84`, and `JntStatsWidget.php:95` all still use `OwnerContext::resolve()` directly for owner resolution. This is the original finding 5's concern — the indirection pattern was applied to the resource query but not to the support/action layer.

2. **`PrintAwbTableAction` uses manual owner-access check.** The `recordIsAccessible()` method (lines 143-159) does a hand-rolled `JntOrder::query()->forOwner(...)` check instead of using `commerce-support`'s `OwnerWriteGuard::findOrFailForOwner()`. Write-path ownership validation should use the shared helper.

3. **`JntStatsWidget` has 4 raw queries** — `calculateOrderStats()` is inline in a widget (lines 60-92 of the widget). The widget is 131 lines with cache logic and 6 stat categories. Consider extracting to a `Support/JntStatsAggregator` service.

4. **No policies** — none of the JNT resources have policies.

### Updated recommendation

Priority 1: Replace remaining `OwnerContext::resolve()` calls with `OwnerUiScope`/`OwnerContext` indirection layer, and add `OwnerWriteGuard` on write actions. Priority 2: Extract stats aggregation from `JntStatsWidget` to a support class. Priority 3: Add policies for all resources.

---

# Filament JNT friendliness review

This note reviews `packages/filament-jnt` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Resources` (3 + abstract `BaseJntResource`)
- `src/Widgets` (1)
- `src/Actions` (5)
- `FilamentJntPlugin.php`
- downstream in `jnt`, `shipping`, `cart`, `checkout`, `orders`

## What is already friendly

### Abstract base resource

- `BaseJntResource.php` (89 lines, the largest of the three abstract bases)

Standard pattern with config-driven nav.

### Shared base Pages for read-only resources

- `Resources/Pages/ReadOnlyListRecords.php`
- `Resources/Pages/ReadOnlyViewRecord.php`

Reuse pattern.

### Tables and Schemas subfolders

- All 3 resources have `Schemas/` + `Tables/`.

Standard layout.

## Findings

### 1. `BaseJntResource` is larger than the other abstract bases

**Files**

- `src/Resources/BaseJntResource.php` (89 lines)

Imports `Carbon\CarbonImmutable`, `Cache`, `OwnerContext` — base handles more than navigation.

**Why this hurts friendliness**

A 89-line base is doing more than nav. It likely handles cache, owner context, and other concerns. Other abstract bases are 55-61 lines.

**Recommendation**

Audit the base. Extract concerns to a `Support/` class. Keep the base thin (nav + ownership).

### 2. Shared base Pages are copy-pasted from `filament-chip`

**Files**

- `src/Resources/Pages/ReadOnlyListRecords.php` (also in `filament-chip`)
- `src/Resources/Pages/ReadOnlyViewRecord.php` (also in `filament-chip`)

**Why this hurts friendliness**

Identical class names across two Filament packages. Copy-paste. If the read-only pattern needs to change, both must be updated.

**Recommendation**

Extract a shared `ReadOnlyListRecords` and `ReadOnlyViewRecord` to a shared location (e.g., `commerce-support` or a new `filament-shared` package).

### 3. Three Actions for printing AWBs

**Files**

- `Actions/PrintAwbTableAction.php` (Table action)
- `Actions/PrintAwbAction.php` (single)
- `Actions/BulkPrintAwbAction.php` (bulk)

**Why this hurts friendliness**

Three different Actions for the same operation (print AWB). The Table action and the single action likely duplicate logic.

**Recommendation**

Keep one Action and let Filament's bulk-selection mechanism handle the bulk case.

### 4. `JntWebhookLogResource` is a developer/audit surface

**Files**

- `src/Resources/JntWebhookLogResource/`

**Why this hurts friendliness**

Webhook logs are typically a developer concern, not an operator concern. Exposing them in the main Filament panel is a navigation pollution.

**Recommendation**

Move to a separate `filament-jnt-devtools` package or hide behind a `Gate::allows('view-jnt-webhook-logs')` check.

### 5. `BaseJntResource` uses `OwnerContext` directly

**Files**

- `src/Resources/BaseJntResource.php`

**Why this hurts friendliness**

`filament-chip`'s `BaseChipResource` uses `scopeForOwner` indirection. `BaseJntResource` uses `OwnerContext` directly. Inconsistency.

**Recommendation**

Use the same indirection pattern. Delegate to `commerce-support`'s `OwnerScope`.

## Concrete refactor plan

### Phase 1 — extract shared base Pages

**Steps**

1. Move `ReadOnlyListRecords` and `ReadOnlyViewRecord` to a shared location.
2. Update `filament-chip` and `filament-jnt` to use the shared bases.

### Phase 2 — collapse AWB print actions

**Steps**

1. Pick one Action (Table action).
2. Remove the others.

### Phase 3 — slim down `BaseJntResource`

**Steps**

1. Extract concerns to `Support/`.
2. Use `commerce-support`'s `OwnerScope` indirection.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — extract shared base Pages

- [done] Move `ReadOnlyListRecords` and `ReadOnlyViewRecord` to a shared location. (Already in `commerce-support`)
- [done] Update `filament-chip` and `filament-jnt` to use the shared bases. (Both already use `AIArmada\CommerceSupport\Filament\Pages\*`)
- [done] Fix test imports to use the shared namespace. (CoverageBoostTest, CoverageGapFillTest)

### Phase 2 — collapse AWB print actions

- [done] Pick one Action (Table action). (`PrintAwbTableAction`)
- [done] Remove the others. (Removed `BulkPrintAwbAction.php`, removed `PrintAwbAction` references from tests)

### Phase 3 — slim down `BaseJntResource`

- [done] Extract concerns to `Support/`. (Created `Support/NavigationBadgeHelper.php` with cache key generation and nav badge counting logic. Base resource now delegates to it.)
- [done] Use `commerce-support`'s `OwnerScope` indirection. (Replaced `OwnerContext::resolve()` + manual `scopeForOwner`/`forOwner()` with `OwnerUiScope::apply()`.)

### Phase 4 — replace remaining OwnerContext::resolve() calls

- [done] Replace `OwnerContext::resolve()` in `NavigationBadgeHelper.php:22` with `OwnerUiScope::resolveOwner()`.
- [done] Replace `OwnerContext::resolve()` in `CancelOrderAction.php:117` with `OwnerWriteGuard` + `OwnerUiScope`.
- [done] Replace `OwnerContext::resolve()` in `PrintAwbTableAction.php:105,153` with `OwnerUiScope::resolveOwner()`.
- [done] Replace `OwnerContext::resolve()` in `SyncTrackingAction.php:84` with `OwnerWriteGuard`.
- [done] Replace `OwnerContext::resolve()` in `JntStatsWidget.php:95` with `OwnerUiScope::resolveOwner()`.

### Phase 5 — adopt OwnerWriteGuard on write actions

- [done] Replace manual `recordIsAccessible()` checks in `PrintAwbTableAction`, `CancelOrderAction`, `SyncTrackingAction` with `OwnerWriteGuard::findOrFailForOwner()`.

### Phase 6 — extract JntStatsWidget query logic

- [done] Extract `calculateOrderStats()` from `JntStatsWidget.php` into `Support/JntStatsAggregator` service class.
- [done] Update `JntStatsWidget` to delegate to `JntStatsAggregator`.

### Phase 7 — add policies for all resources

- [done] Create `JntOrderPolicy`.
- [done] Create `JntTrackingEventPolicy`.
- [done] Create `JntWebhookLogPolicy`.
- [done] Bind all policies in the service provider.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- cross-package tests for jnt/shipping/cart/orders

## Recommended first move

Phase 1 — extract shared base Pages. The copy-paste between `filament-chip` and `filament-jnt` is the most visible structural smell.
