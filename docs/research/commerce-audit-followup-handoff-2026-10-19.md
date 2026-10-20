---
title: Commerce audit follow-up and worker handoff — 2026-10-19
---

# Commerce audit follow-up and worker handoff

Reviewed the current working tree at HEAD `05510acc4a7c6debbb73766bfe11433c8893b891`, including uncommitted and untracked implementation files. Scope: authz, cart, checkout, orders, products, signals, and affected Cashier/payment and Filament integrations. This follows [the October 18 final review](commerce-audit-final-review-handoff-2026-10-18.md).

**Result: seven actionable follow-ups, five P1 and two P2.** The previous fixes substantially improve the implementation, but payment ownership is still enforced differently for returned results, exceptions, recovery, and callbacks. Passing the existing suites does not cover the interleavings below.

This is an implementation handoff, not authorization to deploy. The audit changed no application source. Reproductions use deterministic interleavings; the existing PostgreSQL concurrency suites were also run separately. Do not report these new interleavings as independently verified PostgreSQL races.

## Worker constraints and completion criteria

- No backward compatibility: replace incorrect contracts and paths; do not introduce legacy aliases, shims, dual semantics, or backfills.
- Edit any necessary schema changes in the owning migration in place. Do not add incremental migrations, database foreign keys, or cascades.
- Read `CONTEXT-MAP.md`, owning `CONTEXT.md` files, and applicable rules first. Keep ownership enforcement on all reads, callbacks, and writes.
- Keep gateway I/O outside database transactions. Use the same authoritative attempt identity to decide every state mutation and side effect.
- Write regression tests for the failures below before fixing them. Keep existing successful callback, idempotency transport, cart, refund, and tenant-isolation tests passing.
- Update canonical package docs when public behavior or contracts change.
- Run scoped Pest with `--parallel`, piping output through `2>&1 | tee /tmp/<name>.txt`; use `set -o pipefail`. Run PHPStan level 6 on changed packages and Pint only on changed PHP files.
- Done means all seven items have permanent passing regression tests, scoped verification passes, and there is no route by which an obsolete attempt can mutate or compensate a newer one.

## Implementation order

Fix checkout as one coordinated change: first make attempt outcomes and recovery authoritative (CHK-08), then freeze actual gateway/provider selection (CHK-03), then apply that identity to exceptions and callbacks (CHK-04, CHK-07). This should be a small shared boundary correction around the existing `PaymentAttemptManager`, not additional independent status checks in each entry point. Orders, Authz, and Products can follow independently.

## 1. CHK-08 — P1: recoverable and definitively failed attempts both become stuck

**Sources:** [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php), especially lines 53–76 and 108–147; [ProcessPaymentStep](../../packages/checkout/src/Steps/ProcessPaymentStep.php), result handling after line 127; [CheckoutService](../../packages/checkout/src/Services/CheckoutService.php), `recoverPayment()` and the failure callback branch; [PaymentResult](../../packages/checkout/src/Data/PaymentResult.php), `uncertain()`.

Two end-to-end failures were reproduced:

1. Use the real `ChipProcessor`. Let `Chip::createPurchase()` throw `ConnectionException` after provider acceptance. The processor correctly returns `PaymentResult::uncertain()`. The manager ignores `uncertain`, records a `processing` outcome, and transitions the session to `PaymentFailed`. The pipeline treats it as failure and compensates. Public retry resets the session to `Processing`, but `claim()` observes the recorded non-terminal outcome and never re-drives it. With retry limit 3, only one gateway call occurs and checkout remains stuck; at the final budget, the outer recovery guard also treats the recorded outcome as resolved.
2. Claim attempt 1 and record a redirect payment (`pending`). Deliver its definitive failure callback through `HandleCheckoutPaymentCallback`. The session becomes `PaymentFailed`, but `payment_attempt_outcome.status` remains `pending`. Retry again observes that outcome: no attempt 2 and no gateway call, despite a verified failure and unused budget.

**Impact:** accepted purchases cannot be reconciled through the documented recovery API, and ordinary failed redirect payments cannot start a replacement attempt. Unknown outcomes also run failure compensation before payment truth is known.

**Fix plan:**

- Make outcome classification explicit and durable. Unknown/uncertain, known non-terminal, and definitively failed/cancelled outcomes must have distinct recovery behavior. Persist the uncertainty evidence; do not discard the new DTO flag.
- An uncertain creation response must retain the same generation, actual provider, request identity, and idempotency key. Recovery must reconcile that attempt even at the new-charge retry limit. Do not release a new generation or run definitive-failure compensation merely because creation I/O was uncertain.
- Record verified callback outcomes against the owning generation under the session lock. A definitive failure must release a new generation when budget permits; a pending/processing callback must not do so.
- Calculate recovery eligibility from the same locked authoritative classification used by `claim()`, rather than maintaining divergent guards in `recoverPayment()`.
- Retain observation behavior for ordinary duplicate callers. Define an explicit reconciliation response so callers can distinguish recovery in progress from a newly failed checkout.

**Required tests:** real built-in CHIP timeout through `processCheckout()` then public recovery; equivalent Cashier and CashierChip exception paths; retry limit 1; pending redirect then verified failure then retry; processing result without redirect; repeat and competing recoveries. Assert exact provider calls, unchanged key/generation for uncertainty, incremented generation only after definitive failure, and no compensation/failure events for an unknown outcome.

## 2. CHK-03 — P1: adoption still uses mutable gateway selection; default provider is not frozen

**Sources:** [ProcessPaymentStep](../../packages/checkout/src/Steps/ProcessPaymentStep.php):95–127 and `buildPaymentRequest()`; [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php), `frozenProvider()`; [CashierProcessor](../../packages/checkout/src/Integrations/Payment/CashierProcessor.php), `createPayment()`.

**Reproduction:** claim generation 1 for gateway `old`, provider `provider-old`, and leave the outcome unknown. Change `selected_payment_gateway` to `new` and mutable payment data to `provider-new`. Public recovery adopts generation 1, but invokes the already-resolved `new` processor and sends request gateway `new`, provider `provider-old`, with generation 1's key. The provider field is now preserved when explicitly set; the processor and request gateway are still wrong. If no provider was explicitly selected for Cashier, the claim freezes `null`, and `gateway(null)` consults the current default again after a crash/configuration change.

**Impact:** one logical generation can be submitted through a different payment system, where the original provider's idempotency protection does not apply. Callback routing and later compensation can also target the wrong integration.

**Fix plan:**

- For a new claim, resolve the concrete processor and its actual underlying provider/default before persisting the immutable identity. Add the smallest explicit resolver/processor seam needed; do not leave an unresolved default in a claim.
- For adoption, resolve the processor from the frozen claim gateway, and build request gateway, provider, callback routes, and attribution from that claim. Do not perform availability checks against a newly selected gateway before determining which attempt is being recovered.
- Use the frozen identity for status queries, orphan handling, refunds, and voids. A changed selection applies only to a genuinely new generation after definitive failure.
- Freeze any provider request fields that must remain identical for replay. Do not reuse one key with a newly constructed incompatible payload.

**Required tests:** register two distinct processors and assert which one receives creation; mutate selection between claim and recovery; mutate the Cashier default between claim and recovery without an explicit provider; change mutable payment method and request inputs. Assert the original gateway/provider/key and compatible request payload are retained, including when the newly selected processor is unavailable.

## 3. CHK-04 — P1: a stale thrown exception still fails a newer active generation

**Sources:** [StepExecutor](../../packages/checkout/src/Services/StepExecutor.php), catch branch around lines 138–159; [CheckoutService](../../packages/checkout/src/Services/CheckoutService.php):739–764.

**Reproduction:** start attempt 1 and pause its gateway call. Another flow records a definitive failure and claims attempt 2, leaving attempt 2 in `PaymentProcessing`. Resume attempt 1 with a thrown exception. The catch only excludes persisted `Completed` and `AwaitingPayment`; it does not compare generations. The stale execution marks the payment step failed, compensates, transitions the session to `PaymentFailed`, and dispatches `CheckoutFailed`. The reproduced final row still has `payment_attempts = 2`, with attempt 2's step now `rolled_back`.

The previous completed-callback-winner case is fixed and its permanent tests pass. That does not fence a newer active generation.

**Fix plan:**

- Carry the execution's claimed generation/identity through exception handling, and verify ownership under the row lock before any step-state write, compensation, lifecycle change, error write, or event.
- Route stale exceptions through the same authoritative observation outcome as stale returned results. Do not decide ownership using a list of statuses alone or a request-local process guard; separate workers have separate containers.
- Make the ownership decision and guarded database mutations atomic. A read of status followed by an unconditional save is still vulnerable to a winner arriving between them.
- Preserve a genuine current-attempt failure's behavior and the original exception semantics where the caller still owns the attempt.

**Required tests:** attempt 1 throws after attempt 2 is claimed but before its response; after attempt 2 redirects; and after completion. Assert attempt 2's entire lifecycle/step/error/payment state is unchanged, no compensation targets it, and no stale step-failed/checkout-failed event is emitted. Add a PostgreSQL interleaving for the active-generation window, alongside the existing completed-winner race.

## 4. CHK-07 — P1: an old callback can win during the next attempt's creation window

**Sources:** [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php), `claimNewAttempt()`; [CheckoutService](../../packages/checkout/src/Services/CheckoutService.php):825–837 and success/failure/cancel callback branches; [HandleCheckoutPaymentCallback](../../packages/checkout/src/Actions/HandleCheckoutPaymentCallback.php); [ProcessCheckoutPaymentNotification](../../packages/checkout/src/Actions/ProcessCheckoutPaymentNotification.php).

**Reproduction:** record attempt 1 as failed with ID `pay_old`, then claim attempt 2 without recording its create response. The session still carries `pay_old`. Deliver a paid callback for `pay_old` with `metadata.checkout_attempt = 1`. The ID comparison accepts it because it matches the retained session ID; generation metadata is ignored. Checkout completes and runs `create_order` while `payment_attempts = 2` is still active. A similar gap exists with a null current ID: null is treated as permission for callback-before-response without proving which attempt owns it.

**Impact:** an obsolete receipt finalizes the current attempt while another external payment can still succeed. Rotating redirect tokens protects old browser URLs, but does not bind signed provider notifications to the current generation.

**Fix plan:**

- Validate callback ownership using the frozen gateway, actual provider, generation, and provider payment identity under the session lock. A session reference alone identifies a checkout, not a payment attempt.
- Do not use the previous generation's payment ID as the current generation's identity. Keep any old receipt as attempt-specific evidence if needed; remove its authority over the new attempt.
- Preserve callback-before-create-response only when trusted provider evidence proves the current frozen attempt. Define the metadata/reference contract for each built-in provider, including providers with different metadata support. Fail closed or reconcile when the generation cannot be established.
- Apply the same rule to success, failure, cancel, and status-verification paths. An obsolete callback must not write even a verification failure onto the current attempt.
- Handle a genuinely paid obsolete receipt as an explicit reconciliation/compensation operation tied to that old receipt, without replacing or refunding the winner.

**Required tests:** old success/failure/cancel during attempt 2 before its response; current-generation callback before response; wrong provider with equal amount/ID; missing or mismatched generation evidence; redelivery; current browser token versus rotated token; real provider notification entry points. Assert one order and no mutation of the active generation by an old receipt.

## 5. ORD-08 — P1: changing the parent currency re-denominates received money

**Sources:** [Order](../../packages/orders/src/Models/Order.php), fillable `currency`, cached totals around lines 354–369, and `booted()` around line 467; [OrderPayment](../../packages/orders/src/Models/OrderPayment.php), currency guards; [OrderRefund](../../packages/orders/src/Models/OrderRefund.php), currency guards.

**Reproduction:** create a MYR order for 10,000 and a completed MYR payment for 10,000. Refresh the order and call `update(['currency' => 'USD'])`. The save succeeds. It now reports USD, `getTotalPaid() = 10000`, and `isFullyPaid() = true`, while its actual receipt remains MYR. Child write guards do not protect changes to the parent accounting unit. Existing pending/completed refunds can be stranded by the same parent change.

**Fix plan:**

- Normalize and enforce the accounting currency at the order boundary. At minimum reject changing it once monetary children or reservations exist; a stricter immutable-after-creation contract is acceptable under the no-compatibility policy.
- Define what happens before the first receipt, including existing items and pending payment rows. Do not silently convert numbers or relabel historical receipts/refunds as another currency.
- If allowing a pre-receipt currency edit, serialize it with receipt/refund creation and settlement using a consistent order-first lock boundary. An unlocked existence check followed by save permits a concurrent receipt to invalidate the check.
- Keep cached totals and all accounting consumers in one currency. Update public docs to state when the accounting currency becomes immutable.

**Required tests:** parent edits after completed/partially paid/refunded orders, pending refund reservations, pending payment rows, direct model writes, normalized equivalent currency codes, and a currency edit racing a payment creation. Assert rejected changes leave parent, children, totals, terminal timestamps, and events unchanged.

## 6. AZ-06 — P2: rejected generic team setup can leak the inner scope

**Source:** [Authz](../../packages/authz/src/Authz.php):204–225, especially the setter at line 211 before cleanup is established.

**Reproduction:** configure a valid custom Spatie team resolver whose setter stores the requested ID and then throws `ScopeResolutionException` for `inner-rejected`. Start in `outer`; call `userCanInScope($user, 'any', 'inner-rejected')`. It returns false and never checks the ability, but the active team remains `inner-rejected`. Subsequent work in the request can run under the rejected scope.

**Fix plan:** protect setup and verification as well as callback execution. If the setter/getter fails after changing state, restore the captured previous team and invalidate request-local permission state before propagating/classifying the failure. Preserve the original error; do not mask policy exceptions or execute the callback. Define restoration-failure handling so the service cannot continue silently under an unknown scope.

**Required tests:** both `withScope()` and `userCanInScope()` with a mutating-then-throwing setter; a getter throwing during verification; nested outer scopes; permission relations/cache cleanup; callback call count zero; successful setup and policy exceptions still restore correctly.

## 7. PROD-07 — P2: the new default identity suffix can exceed the SKU column

**Sources:** [MatrixVariantGenerator](../../packages/products/src/Strategies/MatrixVariantGenerator.php):503–511; [owning variants migration](../../packages/products/database/migrations/2001_01_01_000004_create_product_variants_table.php):29.

**Reproduction:** create a product with a valid 244-character SKU and one option value `Red`; run `GenerateVariants`. The new default appends its identity suffix and produces a 256-character variant SKU. The migration declares `string('sku')`, which permits 255 characters on PostgreSQL. The exact 256-character output was verified in SQLite, which does not enforce this limit; a PostgreSQL insertion for this new case was not run.

**Fix plan:** preserve the stable identity suffix while bounding the complete default-generated SKU to the schema capacity. Reserve space for all separators and collision suffixes before trimming the readable prefix. Apply the bound consistently to preparation, queued/batched generation, regeneration, and collision handling. For an explicit custom pattern, reject oversized output before any batch writes rather than silently rewriting the user's pattern. Keep the existing collision and whole-matrix transaction fixes.

**Required tests:** default output at and beyond the boundary, maximum-length parent SKU, multiple options, Unicode, stable regeneration, a collision requiring a longer suffix, and oversized custom output with zero partial variants/pivots across more than 50 combinations. Verify the boundary against PostgreSQL as well as unit tests.

## Previous handoff disposition

| Previous item | Current review |
| --- | --- |
| AZ-02 | Closed for the reported stale non-team SuperAdmin case; current regression passes. |
| AZ-05 | Closed for policy exception propagation; setup-failure cleanup is the separate AZ-06 above. |
| CHK-02 | Creation exceptions are now classified uncertain, preventing the previously demonstrated new-key retry; end-to-end recovery remains broken in CHK-08. |
| CHK-03 | Partially fixed: explicit provider preserved, processor/default provider still mutable. |
| CHK-04 | Completed-winner case fixed, including PostgreSQL race; active newer generation remains unfenced. |
| CHK-05 | Crashed unresolved claim can now recover through the public API at retry limit 1; returned uncertain outcomes still fail in CHK-08. |
| CHK-06 | Protocol idempotency propagation fixed; checkout transport, Cashier, and CashierChip tests pass. |
| CHK-07 | Known different-ID callbacks rejected and browser token rotated; next-generation pre-response window still unsafe. |
| ORD-05 | Specified payment now owner/order-safely reloaded and locked. |
| ORD-06 | Completion decisions now use the locked refund; replay tests pass. |
| ORD-07 | Child currency mismatches rejected; parent mutation still breaks the accounting invariant (ORD-08). |
| CART-07 | Atomic boundary moved into backend; both in-memory regression datasets and PostgreSQL cart suite pass. |
| PROD-05 | Delimiter ambiguity fixed with stable identity suffix; suffix length needs PROD-07. |
| PROD-06 | Custom collision rejection and whole-matrix atomicity implemented; regressions pass. |

## Verification evidence

This section records this review's executed checks. Temporary probes assert the observed defects, so their passing results reproduce problems rather than certify the implementation.

| Check | Result |
| --- | --- |
| Authz package | 78 passed, 269 assertions. |
| Products package | 518 passed, 1,148 assertions. |
| Signals package | 295 passed, 1,573 assertions; 28 opt-in engine tests skipped. |
| Checkout package | 295 passed, 1,233 assertions; 2 opt-in engine tests skipped, then both run on PostgreSQL. |
| Orders package | 470 passed, 1,453 assertions; 7 opt-in engine tests skipped, then all run on PostgreSQL. |
| Cart package | 1,100 passed, 3,120 assertions; 14 skipped (12 opt-in engine tests then passed on PostgreSQL; 2 stress tests remain skipped). |
| Cashier package | 245 passed, 563 assertions. |
| CashierChip idempotency transport | 3 passed, 15 assertions. |
| PostgreSQL cart engine | 12 passed, 308 assertions. |
| PostgreSQL checkout engine | 2 passed, 50 assertions. |
| PostgreSQL orders engine | 7 passed, 480 assertions. |
| PHPStan level 6 | Passed for authz, cart, checkout, orders, products, signals, cashier, and filament-signals. |
| New focused reproductions | 8 defect reproductions passed (37 assertions), plus 1 positive crashed-claim recovery test (3 assertions). |

The Signals implementation, config, schema, and adapter hash comparison found all 188 previously reviewed files unchanged. Its 28 PostgreSQL tests passed in the preceding audit; they were not rerun in this review. No new Signals finding was identified. Existing adapter suites from the preceding audit were not all rerun, and this is not a full repository or live-provider certification.

Source snapshot at audit start: 1,141 files across scoped source/config/migrations/resources, aggregate SHA-256 `107e117e5f50352df5d8926e566c1c3341ea7abf3a1b155f46f96c371f408883`. All 1,141 snapshotted paths were unchanged at the end of review. Temporary audit probes were copied outside the repository and removed from the test suite.

## Worker checklist

- [x] CHK-08: reconcile uncertain attempts and permit new attempts after verified redirect failures.
- [x] CHK-03: freeze and honor the actual gateway/provider across all recovery paths.
- [x] CHK-04: fence thrown errors and side effects by authoritative attempt identity.
- [x] CHK-07: bind callbacks to the current generation, including the pre-response window.
- [x] ORD-08: enforce parent accounting-currency immutability with safe concurrency.
- [x] AZ-06: restore scope after partial setup failures.
- [x] PROD-07: bound complete generated SKUs and reject oversized custom output atomically.
- [x] Run scoped verification, update affected canonical docs, and summarize remaining limitations.
