# Design Record: Combined Promotion and Voucher Policy

- **Task:** DES-DSC-510
- **Chosen design:** Design B — Checkout-owned composition with provider commitment adapters

## Observed facts

1. StackingCoordinationRegistrar has no callers and its boot path is only a comment stub (packages/promotions/src/Support/StackingCoordinationRegistrar.php:1-42).
2. Voucher registration binds a registry, but StackingEngine owns its own rule list and registers defaults in its constructor (packages/vouchers/src/Stacking/StackingEngine.php:42-48, 153-163; packages/vouchers/src/Stacking/StackingRuleRegistry.php:10-41).
3. VoucherService reserve is cache-backed and release is by code rather than a reservation identity (packages/vouchers/src/Services/VoucherService.php:280-335).
4. Checkout applies vouchers through an adapter and rolls them back per code (packages/checkout/src/Integrations/VouchersAdapter.php:30-121; packages/checkout/src/Steps/ApplyDiscountsStep.php:93-110).
5. The promotion listener reads a non-established order.promotion_id (packages/promotions/src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php:14-35).

## Inferences

1. **Inference:** Neither provider owns a combined basket calculation; Checkout is the only context with merchandise, shipping, tax, currency, and both provider outputs.
2. **Inference:** A rule registry disconnected from execution is a dead seam and must be removed, not made more configurable.
3. **Inference:** Provider usage requires a durable reservation token scoped to checkout reference; voucher code alone is unsafe for concurrent sessions.

## Design alternatives

| Dimension | A — Vouchers owns all stacking | B — Checkout composition (chosen) | C — new discounts package |
| --- | --- | --- | --- |
| Depth | Cross-domain takeover | One orchestration boundary | New domain/table/module |
| Leverage | Low for promotions | High for any provider | Medium, premature |
| Locality | Poor | Checkout already has totals | Poor |
| Caller knowledge | Voucher vocabulary leaks | Provider-neutral proposals | New API to learn |
| Test surface | Provider coupled | Composition matrix | Whole new package |
| Migration cost | Medium | Medium | High |

## Chosen design

Checkout owns DiscountCompositionService and consumes tagged optional DiscountProvider adapters for Promotions and Vouchers. Providers return normalized DiscountProposal values and accept a checkout reference for reserve, commit, and release; Checkout never sees voucher codes or promotion IDs as lifecycle handles. A DiscountCommitment persisted in CheckoutSession discount_data contains provider key, provider reservation token, applied minor-unit amount, and immutable calculation fingerprint. DES-FIN-710 becomes the only commitment caller after Order intake.

The cap is min(sum(applied discounts), eligible_merchandise_subtotal_minor). Eligible merchandise subtotal is the sum of discountable cart lines before discounts, tax, and shipping; tax and shipping are excluded in Wave 1. All values are integer minor units in the single checkout currency. Providers submit candidates in deterministic order (priority, provider key, stable candidate key); Checkout allocates remaining cap in that order, so input order cannot change the result. Rounding occurs inside each proposal in minor units; Checkout never rounds fractional money.

If a provider package is absent, its adapter is absent and contributes no proposal; composition still succeeds. A provider failure fails composition rather than silently discounting differently. The registrar and disconnected registry are deleted; provider discovery is by container tag and Checkout sees only the provider-neutral contract.

Reservation is idempotent by (owner, checkout reference, provider key, candidate key). Exact reserve returns the same token; conflicting fingerprint fails. Commit retry returns already committed; release retry returns released. Commit after release and release after commit are typed conflicts. Each provider persists its own reservation/commitment evidence transactionally; Checkout finalization records progress after each durable provider outcome and retries the same token. Promotion usage is recorded from committed discount commitments, not an Order column.

## Implementation scope manifest

### Create

- packages/checkout/src/Contracts/DiscountProvider.php
- packages/checkout/src/Data/DiscountProposal.php
- packages/checkout/src/Data/DiscountCommitment.php
- packages/checkout/src/Services/DiscountCompositionService.php
- tests/src/Checkout/DiscountCompositionServiceTest.php

### Modify

- packages/checkout/src/Steps/ApplyDiscountsStep.php
- packages/checkout/src/Integrations/PromotionsAdapter.php
- packages/checkout/src/Integrations/VouchersAdapter.php
- packages/promotions/src/PromotionsServiceProvider.php
- packages/promotions/src/Listeners/MarkPromotionAsUsedOnOrderPlaced.php
- packages/vouchers/src/VoucherServiceProvider.php
- packages/vouchers/src/Services/VoucherService.php
- packages/vouchers/src/Contracts/VoucherServiceInterface.php
- tests/src/Vouchers/Unit/VoucherServiceTest.php
- tests/src/Vouchers/Unit/Stacking/StackingEngineTest.php
- packages/checkout/docs/04-usage.md
- packages/promotions/docs/04-usage.md
- packages/vouchers/docs/04-usage.md

### Delete

- packages/promotions/src/Support/StackingCoordinationRegistrar.php
- packages/vouchers/src/Stacking/StackingEngine.php
- packages/vouchers/src/Stacking/StackingRuleRegistry.php

## Rejected alternatives

### Rejected: Design A

Vouchers cannot correctly cap promotions without Checkout’s eligible subtotal and tax/shipping knowledge.

### Rejected: Design C

A new package adds a persistence boundary before two provider adapters demonstrate reusable consumers.

## Unknowns

1. Whether later requirements need shipping or tax discounts; that requires an explicit cap-basis version.
2. Whether promotions has durable usage storage that can accept the checkout reference without a new migration.
