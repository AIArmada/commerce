# Pricing friendliness review

This note reviews `packages/pricing` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services`
- `src/Contracts`
- `src/Models`
- `src/Support`
- `src/Settings`
- `src/Data`
- downstream consumers in `cart`, `checkout`, `vouchers`, `promotions`

## What is already friendly

### Real price calculator contract

- `Services/PriceCalculator.php` (impl `Contracts/PriceCalculatorInterface.php`)
- `Contracts/Priceable.php`

This is the right shape. The package has a real contract that any priceable model (Product, Variant, custom Buyable) can plug into, and a single calculator behind it.

### Money result is a DTO

- `Data/PriceResultData.php` (spatie/laravel-data)

The price result is a typed DTO, not an array. Callers can rely on its shape.

### Owner scope is its own class

- `Support/PricingOwnerScope.php`

Owner-scope is a real, named class rather than inline closure logic.

### Settings are split per concern

- `Settings/PricingSettings.php`
- `Settings/PromotionalPricingSettings.php`

The split keeps promotional pricing rules out of the base pricing config.

## Findings

### 1. There is no `Actions/` directory

**Files**

- `src/Services/PriceCalculator.php`
- `src/Models/Price.php`

**Why this hurts friendliness**

`PriceCalculator` is the only public entry point, and it likely owns too much: tier resolution, currency conversion, promotional stacking, and result formatting. New orchestration (price overrides, dynamic pricing rules, tax-inclusive vs tax-exclusive switching) will keep getting added to it.

**Recommendation**

Introduce a small `src/Actions` tree for the most common workflows:

- `Actions/ResolveBasePrice`
- `Actions/ResolveTierPrice`
- `Actions/ApplyPromotionalAdjustment`
- `Actions/FormatPriceForDisplay`

`PriceCalculator` becomes a thin orchestrator that delegates. The calculator still implements the contract, but each step is its own Action.

### 2. Tier resolution logic is likely embedded in `PriceCalculator`

**Files**

- `src/Services/PriceCalculator.php`
- `src/Models/PriceList.php`
- `src/Models/PriceTier.php`

**Why this hurts friendliness**

Tier resolution (which tier applies, how quantities are matched, what currency the tier is in) is a variant-heavy area. New tier strategies (volume-based, customer-segment-based, time-window-based) will edit the same class.

**Recommendation**

Extract a `TierResolverInterface` and one implementation per tier strategy. The calculator selects the resolver and applies it. The strategy can be configured per price list.

### 3. Currency handling is not clearly a seam

**Files**

- `src/Services/PriceCalculator.php`
- `Data/PriceResultData.php`

**Why this hurts friendliness**

The calculator probably handles currency internally. Multi-currency scenarios (FX-based pricing, customer-currency selection, owner-currency overrides) will keep getting added inline.

**Recommendation**

Use `commerce-support`'s `MoneyNormalizer` and `MoneyFormatter` as the boundary. The calculator should accept and return `Money` objects (or DTOs wrapping them), not raw integers and currency strings.

### 4. Promotional pricing settings live in their own file but share the calculator

**Files**

- `Settings/PromotionalPricingSettings.php`
- `Services/PriceCalculator.php`

**Why this hurts friendliness**

The promotional settings are split out, but the calculator still owns the promotional logic. This means changing promotional behavior requires editing the calculator even though the config is in its own file.

**Recommendation**

Move promotional application into a separate `PromotionalPriceResolver` (or similar). The calculator calls it conditionally. The boundary between base pricing and promotional pricing becomes explicit.

### 5. No integration registrar for pricing-aware packages

**Files**

- `src/Support/` (only `PricingOwnerScope.php`)

**Why this hurts friendliness**

Pricing is consumed by cart, checkout, vouchers, and promotions. Each consumer has its own way of asking the pricing package for a price. The pricing package has no opinion about who calls it.

**Recommendation**

Add a `Support/PricingIntegrationRegistrar.php` that wires pricing-aware packages (cart condition provider, voucher resolver). The service provider becomes a composition root, not a wiring hub.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/ResolveBasePrice`, `ResolveTierPrice`, `ApplyPromotionalAdjustment`, `FormatPriceForDisplay`.
2. Make `PriceCalculator` delegate to the Actions.
3. Keep the public contract unchanged.

### Phase 2 — extract tier resolution

**Steps**

1. Add `Contracts/TierResolverInterface`.
2. Add a default tier resolver implementation.
3. Move tier matching logic out of the calculator.

### Phase 3 — adopt `commerce-support` money primitives

**Steps**

1. Make `PriceCalculator` use `MoneyNormalizer` for inputs.
2. Make `PriceResultData` carry a `Money` object (or a `commerce-support`-style DTO) instead of integer + currency.

### Phase 4 — separate promotional pricing

**Steps**

1. Add `Support/PromotionalPriceResolver`.
2. Move promotional logic out of the calculator.
3. Update settings to point at the resolver.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [pending] Add `src/Actions/ResolveBasePrice`, `ResolveTierPrice`, `ApplyPromotionalAdjustment`, `FormatPriceForDisplay`.
- [pending] Make `PriceCalculator` delegate to the Actions.
- [pending] Keep the public contract unchanged.

### Phase 2 — extract tier resolution

- [pending] Add `Contracts/TierResolverInterface`.
- [pending] Add a default tier resolver implementation.
- [pending] Move tier matching logic out of the calculator.

### Phase 3 — adopt `commerce-support` money primitives

- [pending] Make `PriceCalculator` use `MoneyNormalizer` for inputs.
- [pending] Make `PriceResultData` carry a `Money` object (or a `commerce-support`-style DTO) instead of integer + currency.

### Phase 4 — separate promotional pricing

- [pending] Add `Support/PromotionalPriceResolver`.
- [pending] Move promotional logic out of the calculator.
- [pending] Update settings to point at the resolver.



## Suggested verification scope

- `tests/src/Pricing/Unit/PriceCalculatorTest.php`
- `tests/src/Pricing/Unit/PriceListTest.php`
- new tests for the Actions introduced in Phase 1
- tier resolver tests after extraction

## Recommended first move

Phase 1 — introduce the Actions tree. The calculator is the single largest coupling point, and the Actions tree is missing entirely. The split is mostly mechanical.
