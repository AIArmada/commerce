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

- [pending] Move `ReadOnlyListRecords` and `ReadOnlyViewRecord` to a shared location.
- [pending] Update `filament-chip` and `filament-jnt` to use the shared bases.

### Phase 2 — collapse AWB print actions

- [pending] Pick one Action (Table action).
- [pending] Remove the others.

### Phase 3 — slim down `BaseJntResource`

- [pending] Extract concerns to `Support/`.
- [pending] Use `commerce-support`'s `OwnerScope` indirection.



## Suggested verification scope

- per-Resource tests
- per-Action tests
- cross-package tests for jnt/shipping/cart/orders

## Recommended first move

Phase 1 — extract shared base Pages. The copy-paste between `filament-chip` and `filament-jnt` is the most visible structural smell.
