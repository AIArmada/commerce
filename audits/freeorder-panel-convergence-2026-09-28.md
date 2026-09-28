# Free-order inventory deduction — panel convergence (2026-09-28)

Round 1: Bunny HOLD (3 blockers), Luna HOLD (4 blockers). All findings
verified independently against code and by execution before fixing.

## Agreed REAL — fixed

| # | Finding (Bunny / Luna) | Fix |
|---|---|---|
| A | Guard admits fully-paid orders: `getBalanceDue()` is `max(0, grand - paid)`, so any fully-paid order owes "nothing" (D1/M1; Luna spec-mismatch) | Guard is now "no money involved": `grand_total <= 0` (session parity) AND `paid_total === 0`. `PaymentConfirmed` still rejects `amount <= 0`, so the dedicated transition stays justified |
| B | `Processing` early return precedes the balance check: Processing-but-owing returns success and swallows the error; partial payments pass (D7; Luna blocker 1) | Money guards run BEFORE the early return. Processing + owing/paid now throws |
| C | `OnHold → Processing` is a legal graph edge, so a held (paid) order is dragged out of its hold, `held_at` left set (D1-variant/M3; Luna blocker 2) | Explicit source-state allowlist: `Created` / `PendingPayment` only, else `OrderNotAwaitingPayment` (an `InvalidArgumentException` subclass) naming the state. No more implicit `TransitionNotFound` mislabel (D6) |
| D | Session says free, live-cart-repriced order owes: guard throws, `order_id` already persisted, no `compensate` clears it, retry reuses the order → deterministic wedge (D2) | Seam stays the order-keyed decision; `CreateOrderStep` catches the subclass into an `order_state_not_confirmable` record and plain `InvalidArgumentException` into a `session_free_order_mismatch` record, each with its own actionable error. Classification comes from the exception (the seam judged the locked row), never from the step's copy. Failing is correct (money is owed but uncollected); paid-path reconciliation failures wedge identically, so no new recovery machinery |
| E | "End to end" test hand-builds the event; deleting the `afterCommit` block leaves the suite green; `RefreshDatabase` never fires `afterCommit` (D3; Luna coverage gap) | Capture-and-invoke wiring test: the registered callback is captured via a `DB` spy, invoked under `Event::fake`, and must dispatch the real `OrderProcessingStarted` with gateway `free`. Deletion check executed: removing registration fails exactly the wiring test. Listener chain driven twice to prove duplicate delivery deducts once |
| F | Caller instance left stale (`Created` while returned model is `Processing`) (D4) | `syncCallerOrder()` mirrors `PaymentConfirmed::syncOriginalOrder()`, on both the transition and the idempotent-return path. Luna round-2's stale-cache theory refuted by execution (priming test) and framework source |
| G | Missing tests: fully-paid, partial, over-discounted, Processing-owing, seam↔step link (D7); no `OrderPaid`-absence / no-payment-rows assertions; no owner-scope regression (Luna) | All added: 17 tests in `FreeOrderInventoryDeductionTest` (incl. negative-grand accepted to lock `<= 0` parity, cross-owner `RuntimeException`, priming regression, Canceled/failed rejections, PendingPayment success), plus step-level divergence + exception-classification tests |
| H | Doc wording (M4), stale checkout docs (M5), `use Mockery;` warning (N3) | Fixed: precise free-path contract in `05-state-machine.md`, both checkout docs updated, import removed |

## Converged NOT blocking — with evidence

### Luna blocker 3 / Bunny D5: lost after-commit dispatch is unrecoverable

Mechanism is real but it is the codebase's universal commit-then-dispatch
pattern, identical in `PaymentConfirmed` (`handleExistingPayment` returns
silently without re-dispatch) and `OrderHeld`. Blocking the free fix on it
would block the paid path's existence. The cheap alternative —
re-dispatch on idempotent recall — was rejected: it diverges duplicate
semantics from the paid path, heals only opportunistically (a retry must
happen to occur), and turns benign duplicates into listener storms.
Documented as a known limitation; systemic fix (outbox / reconciliation
sweep) filed as follow-up.

### Luna blocker 4: skipping `OrderPaid` "breaks existing" fulfillment

Framing is factually wrong. Verified: `OrderPaid` has exactly one dispatch
site (`PaymentConfirmed.php:120`), unreachable for zero amounts
(`PaymentConfirmed.php:40-41` throws). Free orders therefore NEVER reached
pass issuance, event registration sync, promotion usage counting, invoice
creation, or payment-confirmation emails — before or after this fix.
(Affiliate attribution is likewise unreachable for free orders, but via a
different mechanism: it is inline in `PaymentConfirmed.php:103-105`
through `CommissionAttributionRequired`, not an `OrderPaid` listener.)
Nothing that worked stops working: the change is purely additive for free
orders (`Created → Processing` + `OrderProcessingStarted`). No consumer
anywhere keys on the order-`Created` state — `OrderCreated` itself has
zero listeners in the monorepo — and voucher redemption (the one
free-capable fulfillment path, via `CheckoutCompleted`) reads only
`session->order_id` and discount commitments, never `$order->status`.
The fulfillment gaps are pre-existing and need their own design decision
(zero-amount `OrderPaid`? separate event? per-consumer opt-in?); locking
new fulfillment behavior in via this fix's tests would be scope
expansion, not a defect fix. Documented in `05-state-machine.md`;
follow-up filed.

## Pre-existing facts used in convergence

- `Order::isFullyPaid()` is itself `getBalanceDue() === 0` — the codebase
  already conflates "nothing outstanding" with "fully paid", which is the
  semantic trap behind finding A.
- `paid_at` consumer sweep (Bunny's open item, done here): `isPaid()`,
  revenue cache (`paid_at IS NOT NULL` — correctly excludes free orders),
  receipt/docs fallbacks, Filament paid/unpaid filters, signal recorder —
  all safe with null `paid_at` on a Processing order. Only cosmetic effect:
  operators see Processing + "Not paid", which is true.
- `OrderProcessingStarted` has exactly one consumer (inventory deduction);
  duplicate delivery is absorbed by the `InventoryOperation` unique key +
  completed-status check. Verified by the double-delivery test.

## Round 2

Luna: all 6 round-1 items FIXED except "stale caller instance", held
STILL OPEN on the theory that `setRawAttributes()` leaves a cached
object-valued `status` cast behind. Refuted by execution (priming
regression test green: read `status`, confirm, re-read → `Processing`)
and by framework source (`HasAttributes::setRawAttributes()` clears
both `classCastCache` and `attributeCastCache`). The regression test
stays as a lock-in. Luna accepted limitations A and B (no overturning
evidence) and raised two real nits, both fixed: the reconciliation
reason/message now distinguishes state rejections from total mismatches
(exact: the seam checks money before state, so a money-qualifying order
that throws was state-rejected), and Canceled / PaymentFailed
rejections plus the PendingPayment success path are now covered.

Bunny round 2: SHIP. All six round-1 items verified FIXED against
current code and by execution (incl. independent confirmation of the
Luna refutation: `setRawAttributes()` clears both cast caches, so the
cached `Status` object cannot survive). Limitations A and B not
disputed — B independently strengthened (consumer table re-verified,
voucher carve-out confirmed). Four nits, all addressed: N1
(stale-copy classification → `OrderNotAwaitingPayment` subclass, step
classifies from the exception; stale-row-proof test added); N2
(`confirm_payment` asymmetry → documented as intended: the switch is
payment-specific, the operator-facing manual action cannot process zero
amounts, and gating would strand free orders outside operator-facing
paths, leaving a direct `confirmFreeOrder` service call as the only
recourse); N3 (this record's
staleness → fixed); N4 (reservation-reference doc error → fixed to
cart ID; relation wipe on caller sync → exact parity with
`PaymentConfirmed::syncOriginalOrder`, no live impact, left as is —
but the *return* differs: `FreeOrderConfirmed` returns the locked
copy while `PaymentConfirmed` returns the caller's instance, so the
two objects can silently diverge; no live impact since the only
caller discards the return). The one item Bunny
could not execute (deletion check) was executed here: removing the
`afterCommit` registration fails exactly the wiring test.

## Round 3

Luna: HOLD DEAD at code level — both refutation legs verified from
source (priming test contains the exact trigger; `setRawAttributes()`
clears both cast caches; no remaining stale-status trigger in
relations, `getOriginal()`, or `wasChanged()`). Subclass
classification ACCEPTed (catch order, no misclassified sources,
stale-row-proof test genuine). One fair doc nit: "no recourse" wording
overclaimed — the `confirmFreeOrder` service API is a concrete
application-level recourse; fixed to "operator-facing" in the config
comment, `03-configuration.md`, and this record. Her formal HOLD rests
only on her read-only sandbox blocking test execution (paratest
workers denied under `/var/folders`), not on any code finding. The
execution leg she could not run is green here: 17/17 orders tests,
12/12 step tests, full Orders 371 / Checkout 263 / Inventory 988
suites, Pint + PHPStan L6 clean.

Bunny round 3: SHIP. Refutation dead on two independent legs with
the mechanism chain closed (sync is byte-for-byte the sibling idiom
and matches `refresh()` for attributes, cast caches, and originals —
but not for loaded relations, which `refresh()` reloads and the sync
drops); subclass classification, non-gating
rationale (no recourse path exists today — `OrderForm` status is
disabled, `OrderHoldReleased` needs `OnHold` first, the manual action
is gated on `PendingPayment` while free orders sit in `Created`),
and all doc corrections ACCEPTed; Orders 371 / Checkout 263 re-run
green by execution, PHPStan clean. Three nits, all addressed: N1
(interface docblock "zero-balance" conflation → reworded to the
two-part invariant); N2 (this record overstated return parity →
corrected above); N3 (`syncCallerOrder` wipes unsaved dirty caller
attributes and drops loaded relations — identical to
`PaymentConfirmed`; like `refresh()` for attributes/originals but
unlike it for relations, which `refresh()` reloads — recorded here,
no action).

Note: `syncCallerOrder()` (like its `PaymentConfirmed` sibling)
replaces the caller's attributes wholesale, so any unsaved dirty
attributes on the passed instance are lost and loaded relations
dropped. Unreachable harm today: the only caller passes a freshly
created or freshly loaded order.

## Round 4 (docs-only delta)

Bunny: SHIP. All three wording items factually accurate
("operator-facing" scoping, two-part docblock invariant, N4/N3 record
notes). Process note: the "confirm no runtime code changed via git
diff" check has no referent on an uncommitted tree — verified instead
that the interface diff is docblock + signature only and all other
code diff is the already-audited feature. Next time, snapshot before
the docs pass.

Luna round 4: HOLD on wording/process only. Two fair wording DEFECTs,
both fixed: (1) the old phrasing placed the stranded orders beyond
the API's reach instead of beyond operator paths — now "outside
operator-facing paths, leaving a direct service call as the only
recourse" in the config comment and this record (the
`03-configuration.md` row was already correct);
(2) "matches `refresh()` semantics" overclaimed for loaded relations
(`refresh()` reloads them, the sync drops them — verified at
`Model.php:2186`) — now qualified in both record spots. Item 2
(docblock invariant) ACCEPTed; the underlying non-gating claim
re-verified accurate. Item 4 (git-diff referent) is the known
uncommitted-tree process gap, answered in round 5 with content hashes.

## Round 5 (anchored wording check)

SHA-256 snapshot of every in-scope runtime/test file, taken after the
round-4 wording fixes (docs + record excluded — they are the delta
under review):

- `ff65bfd5…3093f` `packages/orders/src/Transitions/FreeOrderConfirmed.php`
- `53dcbc14…52ab57e4` `packages/orders/src/Exceptions/OrderNotAwaitingPayment.php`
- `bf8f3646…35e8f` `packages/orders/src/Services/OrderService.php`
- `834343c5…68c5c6` `packages/orders/src/Contracts/OrderServiceInterface.php`
- `1bf77979…f94d03` `packages/checkout/src/Steps/CreateOrderStep.php`
- `6ad1a538…a19ee` `tests/src/Orders/FreeOrderInventoryDeductionTest.php`
- `67f859f4…54fe95e` `tests/src/Checkout/CreateOrderStepTest.php`
- `8b124368…14754aa` `tests/src/Checkout/PaymentFlowTest.php`
- `4497e047…34c3d85` `tests/src/Checkout/RegressionTest.php`

Luna round 5: HOLD on one leftover — this record quoted the old
reversed phrasing while describing its own fix (1a DEFECT); 1b ACCEPT,
all 9 hashes MATCH. Fixed by describing the old phrasing without
reproducing it.

## Round 6 (one sentence)

Luna: SHIP. Bunny: SHIP (all three checks pass: no reversed-phrase
matches, record paragraph correct, all 9 hashes match — no runtime or
test file moved). Loop closed: both panels SHIP the final bytes.

## Follow-ups filed (not fixed — out of scope)

1. Free-order fulfillment contract (ticketing / events / promotions /
   attribution / invoice / emails): design decision + implementation.
2. Durable outbox or reconciliation sweep for commit-then-dispatch
   (systemic, covers paid and free paths).
3. Reversal money movement, fraud-review reject void, merchant-ledger
   reject, status-aware payout gate, manual-signal auto-suspend gap,
   accounting asymmetry for off-record conversions (carried over).
4. Cosmetic: Filament "Unpaid Orders" filter vs free Processing orders.
