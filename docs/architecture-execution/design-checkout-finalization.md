# Design Record: Recoverable Checkout Finalization

- **Task:** DES-FIN-710
- **Date:** 2026-07-12
- **Status:** Approved (self-approved, authorized by user)
- **Depends on:** DES-ORD-210, DES-INV-310, DES-DSC-510
- **Chosen design:** Design B — durable phase-driven CheckoutFinalizer

## Observed facts

1. CreateOrderStep creates an order, marks completion, redeems vouchers, commits inventory, clears the cart, and invokes free-order finalization in one step (packages/checkout/src/Steps/CreateOrderStep.php:128-156).
2. FinalizeCheckoutSession independently completes a session and emits CheckoutCompleted (packages/checkout/src/Actions/FinalizeCheckoutSession.php:19-27).
3. CheckoutService invokes the finalizer again after the pipeline (packages/checkout/src/Services/CheckoutService.php:116-123).
4. Free-order finalization exceptions are logged while the step remains successful (packages/checkout/src/Steps/CreateOrderStep.php:146-156).

## Inferences

1. **Inference:** Completion currently has three owners, so a completed session does not prove Order, inventory, discount, and cart effects agree.
2. **Inference:** Local transaction rollback cannot compensate external/provider commitments; finalization needs durable phase evidence and exact retry identities.

## Design alternatives

| Dimension | A — repair CreateOrderStep | B — durable finalizer (chosen) | C — saga service per provider |
| --- | --- | --- | --- |
| Depth | More code in a step | One explicit aggregate workflow | New orchestration framework |
| Leverage | Checkout only, fragile | Reuses all commitment contracts | Premature abstraction |
| Locality | Side effects scattered | Checkout owns completion | Provider logic leaks outward |
| Caller knowledge | Step ordering | finalize(reference) only | Saga vocabulary |
| Test surface | Branch-heavy step tests | Phase/retry table | Distributed framework |
| Migration cost | Low, unsafe | Medium | High |

## Chosen design

Create CheckoutFinalizer, invoked only from CheckoutService after required checkout steps and payment outcome are durable. CreateOrderStep becomes order-intake only: it calls DES-ORD-210 using checkout/session identity and stores the returned order ID; it does not complete, commit, redeem, clear, or emit completion.

CheckoutSession gains durable finalization phase and per-phase result metadata. Ordered phases are: order_intaken; payment_confirmed (or free_payment_resolved); discounts_committed; inventory_committed; checkout_completed; cart_cleared. Each phase stores the reference/token/result needed for exact replay. CheckoutCompleted is emitted once with DB afterCommit only when checkout_completed is durably recorded. Cart cleanup is last because it is not a precondition for a recoverable Order; it retries until recorded complete.

The finalizer locks the session and performs only local state bookkeeping in its short transaction. It invokes each provider outside that transaction, using the DiscountCommitment and Inventory reservation references from the preceding designs, then persists the outcome in a new transaction. A crash therefore resumes the same phase and token. Exact successful retries no-op. A provider rejection leaves the session FinalizationFailed with a retryable/terminal reason; unknown remote outcome stays pending reconciliation and must not advance. Payment is not re-registered: finalization consumes its existing durable outcome. Free and paid flows differ only in their payment phase result, not finalization order.

## Implementation scope manifest

### Create

- packages/checkout/src/Actions/CheckoutFinalizer.php
- packages/checkout/src/Enums/CheckoutFinalizationPhase.php
- packages/checkout/src/Data/FinalizationPhaseResult.php
- tests/src/Checkout/CheckoutFinalizerTest.php

### Modify

- packages/checkout/src/Steps/CreateOrderStep.php
- packages/checkout/src/Actions/FinalizeCheckoutSession.php
- packages/checkout/src/Services/CheckoutService.php
- packages/checkout/src/Models/CheckoutSession.php
- packages/checkout/src/States/CheckoutSessionState.php
- packages/checkout/database/migrations/2024_01_01_000001_create_checkout_sessions_table.php
- packages/checkout/src/Events/CheckoutCompleted.php
- tests/src/Checkout/CreateOrderStepTest.php
- tests/src/Checkout/PaymentFlowTest.php
- packages/checkout/docs/04-usage.md
- packages/checkout/docs/05-checkout-steps.md

### Delete

- packages/checkout/src/Actions/FinalizeCheckoutSession.php — absorbed by CheckoutFinalizer

## Rejected alternatives

### Rejected: Design A

It keeps durable commitments hidden in a pipeline step and retains multiple completion emitters.

### Rejected: Design C

The required variation is sequential durable checkpoints, not a general saga engine.

## Unknowns

1. Whether existing CheckoutSession state persistence can represent FinalizationFailed without a new state.
2. Whether cart clearing is idempotent for all configured cart providers; if not, it needs a cart cleanup token.
