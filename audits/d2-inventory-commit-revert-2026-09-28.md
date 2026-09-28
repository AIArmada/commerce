# D2 inventory commit — audit outcome, revert recommended — 2026-09-28

D2 was locked to (a): a `CommitInventoryOnCheckoutCompleted` listener
mirroring the voucher listener. Both panels audited the implementation
(Luna: HOLD, 2 blockers; Bunny: HOLD, 2 blockers + premise challenge).
I verified every load-bearing claim below against the source.

## The premise was false

The D2 decision assumed nothing commits the reservation on success. In
fact the orders-side path already commits exactly once in the default
config:

1. `create_order` step confirms payment (`confirm_payment` defaults true).
2. `PaymentConfirmed` dispatches `OrderProcessingStarted` via
   `DB::afterCommit` — strictly after the checkout transaction.
3. `DeductInventoryOnPaymentConfirmed` (queued) re-dispatches
   `InventoryDeductionRequired` (integration enabled by default).
4. `DeductInventoryFromOrder::tryCommitAllocations` consumes the cart
   allocations (order metadata carries `cart_id`); only when none
   exist does it fall to `deductDirectly`.
5. The reservation group row later expiring is harmless: its
   allocations are already gone, so the release is a no-op.

## What the new listener breaks (verified)

The listener runs INSIDE the checkout transaction, commits (on-hand
decrement + movement), and DELETES the allocation rows. The afterCommit
path then finds no allocations and deducts directly — a second on-hand
decrement. Luna's precision stands: not literally every order (free
orders skip payment confirmation, untracked items are skipped,
insufficient stock aborts the second leg) — but every paid, tracked,
in-stock order double-decrements, deterministically, invisibly to the
green suite (both paths are mocked apart in tests).

Secondary findings (moot on revert): `not_found` outcomes silently
accepted; commit throws (TTL expiry is a normal condition, plus the
shared-cart `cart_id` collision) roll back order creation after
payment; silent early returns; ambient owner context; stale recorded
state; untestable class-existence guard.

## Recommendation: revert (unanimous) — EXECUTED 2026-09-28

Luna, Bunny, and I all recommended (a) revert; the owner confirmed.
Deleted the listener, its provider registration, and its test. Checkout
suite post-revert: 259 passed (262 minus the 3 D2 tests).

Footnote: free orders never reach path B (no payment confirmation),
so after the revert their reservations expire undeducted — the
pre-existing state, and a separate gap (free-order stock accounting),
not a reason to keep a listener that corrupts paid orders.

Raw panel outputs: `/tmp/panel-d2-luna.txt`,
`/tmp/panel-d2-bunny.txt`, `/tmp/panel-luna-d2-a.txt`.
