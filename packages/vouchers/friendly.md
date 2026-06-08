# Vouchers friendliness review

This note reviews `packages/vouchers` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services` (3 classes)
- `src/Stacking` (engine + 8 rules)
- `src/Compound` (engine + 5 conditions + 5 matchers)
- `src/Listeners` (2 classes)
- `src/Models` (4 classes)
- `src/Events` (2 classes)
- `src/Conditions`
- `src/Support`
- downstream consumers in `cart`, `checkout`, `affiliates`, `signals`

## What is already friendly

### Real service contract

- `Services/VoucherService.php` (impl `Contracts/VoucherServiceInterface.php`)

### Real product matcher contract

- `Contracts/ProductMatcherInterface.php`

This is the right shape. New product-matching strategies (attribute, category, SKU, price) can implement the contract.

### Stacking engine is rule-driven

- `Stacking/StackingEngine.php`
- `Stacking/StackingPolicy.php`
- `Stacking/StackingDecision.php`
- `Stacking/Rules/{CampaignExclusion, CategoryExclusion, MaxDiscount, MaxDiscountPercentage, MaxVouchers, MutualExclusion, TypeRestriction, ValueThreshold}Rule.php` (all impl `Stacking/Contracts/StackingRuleInterface.php`)

This is the right pattern. Stacking rules are first-class classes behind a contract.

### Compound voucher engine is strategy-driven

- `Compound/CompoundVoucherCondition.php`
- `Compound/BundleVoucherCondition.php`
- `Compound/BOGOVoucherCondition.php`
- `Compound/CashbackVoucherCondition.php`
- `Compound/TieredVoucherCondition.php`
- `Compound/Matchers/{AttributeMatcher, CategoryMatcher, CompositeMatcher, PriceMatcher, SkuMatcher}.php`

Each voucher family is its own condition class, and each product matcher is its own class. This is the most extensible part of the package.

### Cart integration is isolated

- `Cart/VoucherConditionProvider.php`
- `Support/CartManagerWithVouchers.php`
- `Support/CartWithVouchers.php`

Cart composes vouchers through a provider, not by reaching into the voucher model.

### Affiliate integration registrar exists

- `Support/AffiliateIntegrationRegistrar.php`

The package plugs into affiliates through a registrar, not by editing foundation.

## Findings

### 1. There is no `Actions/` directory

**Files**

- `src/Services/VoucherService.php`
- `src/Services/VoucherValidator.php`
- `src/Services/VoucherDiscountCalculator.php`

**Why this hurts friendliness**

`VoucherService` is the public surface, but mutations (apply, remove, expire, refill) likely live inline. The validator and discount calculator are siblings, but the orchestration between them is in `VoucherService`.

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/ApplyVoucherToCart`
- `Actions/RemoveVoucherFromCart`
- `Actions/ValidateVoucherCode`
- `Actions/RecordVoucherUsage`
- `Actions/ExpireVoucher`

`VoucherService` becomes a thin facade that delegates. The validator and discount calculator stay as read-side services.

### 2. Service count (3) and Action count (0) is inconsistent

**Why this hurts friendliness**

Like inventory and shipping, the package has services but no Actions. The monorepo rule is "Actions only, no logic in services" and this package is the exception.

**Recommendation**

Same as inventory/shipping: move all mutations to Actions.

### 3. Stacking rules are real variants but registration is not a seam

**Files**

- `src/Stacking/Rules/*`

**Why this hurts friendliness**

The 8 rule classes look like a great seam, but adding a new rule may require editing the engine or the rule loader rather than just registering a new class.

**Recommendation**

Add a `StackingRuleRegistry` (or tagged binding) so new rules can be registered from outside the package. Built-ins register in the service provider.

### 4. Stacking engine is a real engine but the policy class is unclear

**Files**

- `src/Stacking/StackingEngine.php`
- `src/Stacking/StackingPolicy.php`
- `src/Stacking/StackingDecision.php`

**Why this hurts friendliness**

`StackingPolicy` and `StackingDecision` are sibling classes. It is unclear what role each plays and which one is the entry point.

**Recommendation**

Audit the stacking trio. Either `StackingEngine` is the orchestrator and `StackingPolicy` is the input, or `StackingPolicy` is the orchestrator and `StackingEngine` is the rule runner. Pick one canonical entry point and document it.

### 5. Listeners repeat validation logic

**Files**

- `src/Listeners/IncrementVoucherAppliedCount.php`
- `src/Listeners/ValidateVoucherOnCheckout.php`

**Why this hurts friendliness**

Both listeners may call into the validator or service. If the same validation is run at apply time and checkout time, the rules may drift.

**Recommendation**

Extract a `ValidateVoucherAction` and have both listeners call it. The action owns the validation policy.

### 6. Two rule catalogs in `Support/`

**Files**

- `src/Support/VoucherRulesFactory.php`

**Why this hurts friendliness**

The factory may overlap with `RulePresets`-style catalogs in other packages (cart has a similar pattern). If the same rule definitions appear in multiple places, the rules will drift.

**Recommendation**

Audit `VoucherRulesFactory` against the cart's `BuiltInRulesFactory` and `RulePresets`. If they overlap, consider a shared rule catalog in `commerce-support`.

### 7. Events are only 2

**Files**

- `src/Events/VoucherApplied.php`
- `src/Events/VoucherRemoved.php`

**Why this hurts friendliness**

Downstream packages (signals, affiliates, analytics) need to react to voucher lifecycle events. The package emits only apply/remove, not create, expire, or refill.

**Recommendation**

Add events:

- `Events/VoucherCreated`
- `Events/VoucherExpired`
- `Events/VoucherRefilled`
- `Events/VoucherUsageRecorded`

### 8. Voucher state machine is a real seam

**Files**

- `src/States/{Active, Depleted, Expired, Paused, VoucherStatus}.php`

This is the right shape (spatie/laravel-model-states). Keep using it as the package grows.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/ApplyVoucherToCart`, `RemoveVoucherFromCart`, `ValidateVoucherCode`, `RecordVoucherUsage`, `ExpireVoucher`.
2. Move orchestration out of services.
3. Update listeners and cart integration.

### Phase 2 — clarify the stacking trio

**Steps**

1. Audit `StackingEngine` vs `StackingPolicy` vs `StackingDecision`.
2. Pick the canonical entry point.
3. Document the public API.

### Phase 3 — add stacking rule registry

**Steps**

1. Add `Stacking/StackingRuleRegistry`.
2. Register built-ins from the service provider.
3. Document the registration pattern.

### Phase 4 — add voucher lifecycle events

**Steps**

1. Add the missing events.
2. Dispatch from the new Actions.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/ApplyVoucherToCart`, `RemoveVoucherFromCart`, `ValidateVoucherCode`, `RecordVoucherUsage`, `ExpireVo...
- [pending] Move orchestration out of services.
- [pending] Update listeners and cart integration.

### Phase 2 — clarify the stacking trio

- [pending] Audit `StackingEngine` vs `StackingPolicy` vs `StackingDecision`.
- [pending] Pick the canonical entry point.
- [pending] Document the public API.

### Phase 3 — add stacking rule registry

- [pending] Add `Stacking/StackingRuleRegistry`.
- [pending] Register built-ins from the service provider.
- [pending] Document the registration pattern.

### Phase 4 — add voucher lifecycle events

- [pending] Add the missing events.
- [pending] Dispatch from the new Actions.



## Suggested verification scope

- per-Action tests for new mutation Actions
- stacking engine tests
- compound voucher tests
- cross-package tests for cart/checkout/affiliates/signals after refactor

## Recommended first move

Phase 1 — introduce the Actions tree. The package has a real stacking engine and a real compound engine, but no Actions. The Actions split is mostly mechanical and unblocks later cleanup.
