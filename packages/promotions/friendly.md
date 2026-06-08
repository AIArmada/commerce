# Promotions friendliness review

This note reviews `packages/promotions` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services`
- `src/Models`
- `src/Contracts`
- `src/Enums`
- `src/Support`
- `src/PromotionsServiceProvider.php`
- downstream consumers in `cart`, `vouchers`, `checkout`

## What is already friendly

### Real service contract

- `Services/PromotionService.php` (impl `Contracts/PromotionServiceInterface.php`)

Callers depend on the contract, not the concrete service.

### Owner scope is its own class

- `Support/PromotionsOwnerScope.php`

Owner-scope is a real, named class rather than inline closure logic.

### Promotion type is an enum

- `Enums/PromotionType.php`

Variant families are named explicitly.

## Findings

### 1. There is no `Actions/` directory

**Files**

- `src/Services/PromotionService.php`
- `src/Models/Promotion.php`

**Why this hurts friendliness**

`PromotionService` is the only entry point. Every promotion workflow (create, deactivate, attach to product, evaluate against cart) is in one class. New promotion types (BOGO, tiered, time-windowed) will keep getting added to the same class.

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/CreatePromotion`
- `Actions/DeactivatePromotion`
- `Actions/AttachPromotionToProduct`
- `Actions/EvaluatePromotionForCart`
- `Actions/ApplyPromotionToCart`

`PromotionService` becomes a thin facade that delegates to the Actions.

### 2. Promotion evaluation is likely embedded in the service

**Files**

- `src/Services/PromotionService.php`
- `src/Models/Promotion.php`
- `Enums/PromotionType.php`

**Why this hurts friendliness**

Evaluation logic (which promotion applies, in what order, with what stacking rules) is a variant-heavy area. As the package supports more promotion types, the service will keep growing.

**Recommendation**

Extract a `PromotionStrategyInterface` and one implementation per `PromotionType`. The service resolves the strategy and applies it. Adding a new type means adding a strategy class, not editing the service.

### 3. Stacking rules likely live inside the model or service

**Files**

- `src/Models/Promotion.php`

**Why this hurts friendliness**

Stacking (can two promotions apply at once, which takes precedence, what's the max discount) is shared concern with vouchers. If both packages keep their own stacking rules, the rules will drift.

**Recommendation**

Coordinate with `vouchers` (which already has a `StackingEngine`). Consider extracting a shared stacking primitive to `commerce-support` if the same patterns appear in both.

### 4. No `Events/` directory

**Files**

- (none)

**Why this hurts friendliness**

Downstream packages (cart, signals, affiliates) need to react to promotion lifecycle events. Without explicit events, the integration is by polling or direct calls.

**Recommendation**

Add events:

- `Events/PromotionCreated`
- `Events/PromotionUpdated`
- `Events/PromotionDeactivated`
- `Events/PromotionApplied`
- `Events/PromotionRemoved`

### 5. No `Console/Commands`

**Why this hurts friendliness**

Bulk operations (deactivate expired promotions, refresh product attachments, recompute eligibility) have no clear owner.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed.

### 6. No `Listeners/`

**Why this hurts friendliness**

If the package ever needs to react to external events (cart updated, order paid, voucher applied), it has no listener surface today.

**Recommendation**

Add a `src/Listeners` directory when the first cross-package reaction is needed.

### 7. The provider is small today

**Files**

- `src/PromotionsServiceProvider.php`

**Why this is worth noting**

The provider is currently lean. This is the right starting state. Use the monorepo's tagged-registrar pattern when strategies and integration points start to multiply.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreatePromotion`, `DeactivatePromotion`, `EvaluatePromotionForCart`, `ApplyPromotionToCart`.
2. Move orchestration out of `PromotionService`.
3. Update downstream callers.

### Phase 2 — extract promotion strategies

**Steps**

1. Add `Contracts/PromotionStrategyInterface`.
2. Add one strategy per `PromotionType`.
3. Register built-ins in the provider.

### Phase 3 — add domain events

**Steps**

1. Add `Events/PromotionCreated`, `PromotionDeactivated`, `PromotionApplied`, `PromotionRemoved`.
2. Dispatch from the new Actions.
3. Update signals/cart listeners.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/CreatePromotion`, `DeactivatePromotion`, `EvaluatePromotionForCart`, `ApplyPromotionToCart`.
- [pending] Move orchestration out of `PromotionService`.
- [pending] Update downstream callers.

### Phase 2 — extract promotion strategies

- [pending] Add `Contracts/PromotionStrategyInterface`.
- [pending] Add one strategy per `PromotionType`.
- [pending] Register built-ins in the provider.

### Phase 3 — add domain events

- [pending] Add `Events/PromotionCreated`, `PromotionDeactivated`, `PromotionApplied`, `PromotionRemoved`.
- [pending] Dispatch from the new Actions.
- [pending] Update signals/cart listeners.



## Suggested verification scope

- per-Action tests for new mutation Actions
- strategy tests
- cross-package tests for cart/vouchers/checkout after refactor

## Recommended first move

Phase 1 + Phase 2 together — introduce the Actions tree and extract promotion strategies. The single-service + single-model shape is the most visible sign of missing seams, and the split is mostly mechanical.
