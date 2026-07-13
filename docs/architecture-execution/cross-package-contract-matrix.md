# Cross-Package Contract Matrix

- **Task:** CTR-620
- **Date:** 2026-07-12
- **Status:** Approved (self-approved, authorized by user; contracts now active)

## Observed facts

1. DES-ORD-210 chooses immutable Order intake identity (owner, intake_source, intake_id) and returns an existing Order for an exact retry.
2. DES-INV-310 chooses an Inventory-owned reservation group keyed by owner plus checkout reference; allocation IDs are not external handles.
3. DES-DSC-510 chooses Checkout composition plus provider-neutral discount commitments.
4. DES-FIN-710 chooses one phase-driven finalizer and moves all commitment calls out of CreateOrderStep.

## Inferences

1. **Inference:** Checkout session ID is the canonical correlation reference; cart ID remains cart identity and cannot be assumed immutable across retries.
2. **Inference:** No local database transaction can atomically cover provider state; durable phase evidence bridges each local commit.

## Contract matrix

| Concept | Producer | Consumer | Shape/invariant | Conflict or absence |
| --- | --- | --- | --- | --- |
| Correlation | CheckoutSession | Order, Inventory, Discount, Finalizer | `checkout:{session UUID}`, immutable; used as intake ID/reference | Same value + different fingerprint is conflict |
| Owner | OwnerContext | every package | morph type + UUID; explicit global only | unresolved enabled scope throws |
| Line | cart snapshot | Inventory, Discounts, Order | product UUID, nullable variant UUID, positive integer quantity | normalized snapshot fingerprint mismatch fails |
| Money | pricing/tax snapshot | Discounts, Order | integer minor units + ISO currency; one checkout currency | currency mismatch fails |
| Order intake | Orders | Finalizer | source=`checkout`, id=session UUID; Order ID/result fingerprint | exact retry returns same order; conflict throws |
| Inventory | Inventory | Finalizer | reference, state, expiry, aggregate line quantities, order ID | absent=`not_managed`, never fake ID |
| Discount | providers | Finalizer | provider key, reservation token, applied minor units, fingerprint | absent provider contributes no proposal |
| Completion | Finalizer | Checkout events/cart | ordered durable phase result | cannot emit before commitments terminal |

## Chosen design

The sequence is: snapshot and calculate → reserve discounts and Inventory → obtain durable payment result → create/reuse Order through intake → Finalizer commits discounts → Finalizer commits Inventory → record CheckoutCompleted after commit → clear cart. Each phase has the checkout reference, owner context, request fingerprint, and observable result. A failure after a provider succeeds records that phase as pending/unknown and retries/reconciles the same provider token; compensation is allowed only for still-reserved discounts/inventory before their commit. Payment, committed discounts, and committed inventory are retry-only, not compensated by a blind local rollback.

Paid success uses every phase in order. A crash after remote/provider success resumes that phase and receives its stable result. Concurrent payment callbacks serialize on CheckoutSession/finalization phase and Order intake unique identity. Optional Inventory returns not_managed; absent Promotion/Voucher adapters yield no candidates. Checkout never consumes allocation rows, voucher codes as reservation handles, or provider storage IDs.

## Implementation scope manifest

- packages/orders/src/Actions/CreateOrder.php
- packages/orders/src/Contracts/OrderServiceInterface.php
- packages/orders/src/Models/Order.php
- packages/checkout/src/Actions/CheckoutFinalizer.php
- packages/checkout/src/Models/CheckoutSession.php
- packages/checkout/src/Steps/CreateOrderStep.php
- packages/checkout/src/Steps/ApplyDiscountsStep.php
- packages/checkout/src/Steps/ReserveInventoryStep.php
- packages/inventory/src/Contracts/CheckoutReservationServiceInterface.php
- packages/inventory/src/Services/Stock/CheckoutReservationService.php
- packages/checkout/src/Services/DiscountCompositionService.php
- packages/checkout/src/Contracts/DiscountProvider.php

## Rejected alternatives

### Rejected: cart ID as canonical correlation

Cart reuse makes it weaker than the CheckoutSession UUID and it cannot safely identify a finalization replay.

### Rejected: one cross-package database transaction

Independent package/provider effects cannot participate in a single reliable transaction.

## Unknowns

1. Integration owner must confirm the exact CheckoutSession locking column/state transition.
2. Reviewer must approve the migration treatment for existing active cart allocations and cached voucher reservations.
