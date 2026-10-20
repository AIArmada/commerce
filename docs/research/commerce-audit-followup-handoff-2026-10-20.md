---
title: Commerce audit follow-up and worker handoff — 2026-10-20
---

# Commerce audit follow-up and worker handoff

Reviewed HEAD `05510acc4a7c6debbb73766bfe11433c8893b891` and the current uncommitted/untracked implementation after the [October 19 handoff](commerce-audit-followup-handoff-2026-10-19.md). Scope remains authz, cart, checkout, orders, products, signals, and their affected payment/adapter boundaries.

**Four follow-ups remain: two P1 application defects, one P2 application defect, and one P2 test-isolation defect.** The original uncertainty, provider-selection, pre-response callback, currency, scope-restoration, and SKU-length examples now have fixes and permanent regressions. The remaining application problems are in checkout integration boundaries and the interval after an ownership check releases its lock.

No application source was changed by this audit. The four temporary application probes passed by asserting the observed defects: four tests, 20 assertions. They reproduce three application findings, including two independent inbound paths for CHK-09. The PostgreSQL test-isolation failure was observed separately in the existing suite; it is not evidence of a provider or production payment failure.

## Worker contract

- No backward compatibility: replace incorrect contracts, remove legacy fallbacks, and do not add shims, aliases, dual semantics, or backfills.
- Edit any necessary schema changes in the owning migration in place. Do not add incremental migrations, foreign-key constraints, or cascades.
- Read `CONTEXT-MAP.md`, owning `CONTEXT.md` files, and matching rules before implementation. Keep owner scoping on every path.
- Keep external gateway I/O outside database transactions. An ownership read under a lock is insufficient if later writes occur after releasing it.
- Add failing regression tests for the cases below, then implement the correction. Preserve the new successful uncertainty recovery, stale-generation observation, callback attribution, currency, Authz, and Products tests.
- Update canonical package docs where behavior changes. Run scoped Pest with `--parallel` and `2>&1 | tee /tmp/<log>.txt` under `set -o pipefail`; run PHPStan level 6 on changed packages and Pint only on changed PHP files.
- Treat this as a fix handoff, not deployment approval. The audit deliberately preserves the user's existing working tree.

## 1. CHK-04 — P1: ownership is lost after the failure guard and before compensation finishes

**Sources:** [CheckoutService](../../packages/checkout/src/Services/CheckoutService.php):788–814; [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php), `stillOwnsFailure()`; [StepExecutor](../../packages/checkout/src/Services/StepExecutor.php):228–232; [CheckoutSession](../../packages/checkout/src/Models/CheckoutSession.php):247–263.

The new generation check fixes a stale exception that arrives after attempt 2 is already claimed. However, `stillOwnsFailure()` releases its transaction/row lock before `handleCheckoutFailure()` starts compensation and writes lifecycle/error state. Compensation then saves the stale session's entire `payment_data` blob without a generation guard.

### Reproduction

1. Start attempt 1. Its gateway throws. The guarded step-failure write and `stillOwnsFailure()` both correctly see that attempt 1 owns the row.
2. Pause the old flow in a compensation handler. Another flow records a definitive failure for attempt 1 through `PaymentAttemptManager::record()` and claims attempt 2 through `claim()`.
3. Resume the old compensation. `recordCompensation()` saves its old `payment_data`, then the old service transitions to `PaymentFailed`, writes an error, and dispatches `CheckoutFailed`.
4. The reproduced final row has **`payment_attempts = 2` but `payment_data.payment_attempt.number = 1`**, plus a failed lifecycle. The replacement's frozen identity has been overwritten, not merely its display state.

The deterministic probe stages the second worker inside a compensation callback, after the initial lock has been released. It uses the real manager methods for the definitive result and replacement claim; it does not bypass the claim protocol.

### Impact

A timed-out older execution can erase a replacement attempt's identity, roll back its step state, and publish failure over it. The counter/identity mismatch can subsequently be classified as a new generation, undermining the protection against a second external charge.

### Fix plan

1. Replace the read-then-act failure check with an attempt-owned failure/compensation operation. Capture the generation and exact resources/payment identifiers that the failing execution is allowed to compensate.
2. Claim that operation durably under the session lock. Define how replacement claims interact with compensation in progress, particularly for shared inventory/order side effects. Do not hold the lock across gateway I/O.
3. Execute only the captured attempt's compensation outside the transaction. Recheck the operation token/generation for every subsequent state, compensation-log, error, and lifecycle write, using locked fresh data or a conditional update.
4. Merge compensation evidence into the authoritative current row or store it per attempt. Never save an old whole-session JSON blob over a new claim.
5. Fence notifications/events as part of the authoritative outcome. A stale operation must observe the replacement without failing or compensating it.
6. Apply the same boundary to returned step failures, validation failures, and `handleCheckoutFailureResult()`, which still lacks a generation token. Do not fix only the thrown-exception branch.

### Acceptance tests

- Pause after the ownership decision, claim attempt 2 while old compensation is blocked, then resume. Assert the entire replacement claim, key, outcome, step map, payment ID, lifecycle, and error state survive.
- Repeat when attempt 2 records a redirect or completes during compensation.
- Cover both thrown gateway errors and returned definitive failures; assert no stale failure event or replacement payment refund/void.
- Assert the counter and frozen claim generation remain consistent after every interleaving and recovery does not release attempt 3 as a consequence of stale JSON.
- Add this precise post-check interval to the PostgreSQL races. Existing tests that claim attempt 2 before the stale exception do not cover it.

## 2. CHK-09 — P1: inbound routing rejects the frozen gateway after recovery

**Sources:** [PaymentCallbackController](../../packages/checkout/src/Http/Controllers/PaymentCallbackController.php):219; [ProcessCheckoutPaymentNotification](../../packages/checkout/src/Actions/ProcessCheckoutPaymentNotification.php):89–101; [ProcessPaymentStep](../../packages/checkout/src/Steps/ProcessPaymentStep.php), recovery selection and callback URL construction.

Recovery now creates/re-drives through the correct frozen gateway, but both inbound filters still authorize the route against mutable `selected_payment_gateway`.

### Reproduction

1. Claim attempt 1 on `chip`, leave it unresolved, and change the selected gateway to `cashier`.
2. Public recovery correctly adopts attempt 1, uses CHIP, and records a CHIP redirect/payment ID. The selected gateway remains `cashier`, while `payment_attempt.gateway` is `chip`.
3. Send the recovered browser return through the actual named `checkout.payment.chip.success` route with the current valid callback token. The controller returns the failure redirect with **“Checkout session not found”**, because its gateway check compares against `cashier`.
4. Separately deliver a current-generation CHIP notification with the correct payment ID/reference and purchase metadata through `ProcessCheckoutPaymentNotification` with trusted expected gateway `chip`. It is ignored by `gatewayMatches()`, leaving the checkout awaiting payment.
5. In the notification probe, restoring only the mutable selection to `chip` makes the identical payload complete successfully. This isolates the rejection to the outer routing filter, not callback ownership or amount evidence.

### Impact

A recovered charge can succeed at the original provider while both the customer's return and its genuine provider notification fail to settle checkout. The service-level frozen-gateway tests pass because they enter after these filters.

### Fix plan

1. Provide one authoritative current-attempt gateway resolver shared by payment execution and inbound adapters. For an active claimed generation, the frozen gateway controls callback routing; selection controls only a genuinely new generation.
2. Use it in browser success/failure/cancel route validation and notification `expectedGateways` matching. Preserve gateway-specific signature verification and provider identity checks; do not infer trust from an arbitrary payload gateway.
3. Revalidate the gateway against the locked current attempt before mutation, so an outer read cannot authorize the wrong generation after a concurrent retry.
4. Keep rotated callback-token enforcement and metadata generation checks. Do not make a mutable selection assignment the security primitive or add a legacy routing path to work around the mismatch.
5. Document that selecting the next gateway cannot redirect the active attempt's inbound traffic.

### Acceptance tests

- Recover CHIP while mutable selection is Cashier and exercise actual named HTTP success/failure/cancel routes with the current token.
- Deliver a verified current-generation notification through the real notification action/listener, not directly to `handlePaymentCallback()`.
- Cover Cashier's frozen underlying provider, unavailable newly selected processors, a genuine new generation on the newly selected gateway, obsolete notifications, and wrong-route/wrong-provider rejection.
- Assert one order/settlement and that no valid current receipt is stranded by a changed selection.

## 3. CHK-10 — P2: adoption mutates a nullable payment method and rebuilds the provider payload

**Sources:** [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php):715–741, especially line 730; [ProcessPaymentStep](../../packages/checkout/src/Steps/ProcessPaymentStep.php), `buildPaymentRequest()`; [ChipPurchasePayloadBuilder](../../packages/checkout/src/Support/ChipPurchasePayloadBuilder.php), mutable billing/client fields.

### Reproduction

Start an attempt with no explicit payment method and billing email `old@example.test`. Return an uncertain creation response. Change the session's mutable `payment_method` to `pm_new` and billing email to `new@example.test`, then recover it publicly.

The second provider invocation uses **the same idempotency key** but a different method and email. `adopt()` treats a valid frozen null method as absent and replaces it from mutable data; request construction and the CHIP client builder also reread live billing fields. The frozen claim does not include the full provider-relevant request snapshot.

### Impact

Recovery no longer replays the original operation. A provider that checks request consistency for an idempotency key can reject it, leaving an accepted/unknown attempt unreconciled. Providers may differ in how they handle changed fields; the probe verifies the changed request and reused key, not a live-provider response.

### Fix plan

1. Preserve explicit nullable fields exactly: null is a legitimate frozen “no explicit method” value, not permission to consult mutable selection during adoption.
2. Freeze the provider-relevant creation request before the first external call. Include the actual customer/billable identity, method, billing/client fields, metadata, callback URLs, and other inputs that are sent with that idempotency key. Existing amount/currency/gateway/provider fields are already frozen and should stay so.
3. Reconstruct both `PaymentRequest` and provider payloads from the same snapshot on reconciliation. Remove direct live-session billing reads from replay construction, or pass their frozen equivalent explicitly.
4. Require complete current claim data; remove fallback behavior that reconstructs missing historical fields from current mutable data. No compatibility requirement justifies a second interpretation.
5. Apply newly selected inputs only to a new generation after definitive failure. Keep sensitive provider request data appropriately scoped and avoid logging it as debugging evidence.

### Acceptance tests

- First method null, then mutable method set; first method set, then mutable method changed/cleared.
- Change billing email/address, customer/billable association, callback configuration, and metadata between an uncertain result and recovery. Assert equivalent provider request payloads and unchanged key/generation.
- Test the real Cashier/Stripe and CHIP option builders, rather than only a DTO fake. Assert no live billing field bypasses the snapshot.
- Verify a genuine replacement generation uses the new input values and a new key.

## 4. TEST-01 — P2: checkout engine workers share a destructively reset race ledger

**Sources:** [CheckoutEngineTestCase](../../tests/src/Checkout/CheckoutEngineTestCase.php), `engineParams()`, `useEngine()`, and `provisionRaceLedger()`; [CheckoutStaleThrownExceptionRaceTest](../../tests/src/Checkout/Engine/CheckoutStaleThrownExceptionRaceTest.php):528.

### Observed verification failure

The normal three-process engine invocation produced **one failure, two passes**. The completed-callback winner's expected accepted-charge ledger count was 1 but actually 0; both child flows had reported success. The same three tests then passed with `--parallel --processes=1` against a separate disposable database (69 assertions).

The base case gives each parallel worker the same `CHECKOUT_ENGINE_PGSQL_DATABASE`, and every `useEngine()` drops/recreates the shared `checkout_engine_creates` table. Tests also issue unscoped ledger deletes. Another test can therefore erase an in-flight test's provider ledger or evidence. The source establishes the isolation defect; the failing run did not instrument the exact worker that removed the row.

### Fix plan

1. Allocate a disposable database or schema using both a run identifier and a top-level parallel worker/test identifier, retaining the `chkeng_` safety guard. Use the runtime's supported worker token; confirm the installed Pest/ParaTest API rather than guessing it.
2. Ensure each race's intentionally cooperating child processes inherit that exact isolated database/schema. Child participants in one race must still see each other's committed rows.
3. Provision the ledger without dropping another test's table. Use unique per-test/session ledger ownership and scoped cleanup; never delete another session's accepted-charge records.
4. Ensure migrations/provisioning follow the same isolation boundary, including repeated invocations and concurrent runs.
5. Do not mark the test skipped, remove the count assertion, or permanently force one process to conceal the shared-state problem.

### Acceptance tests and command

Run the whole engine directory repeatedly with at least three top-level processes. All three tests must pass together; each race must keep its own accepted-charge evidence. Also run two independent invocations concurrently against the chosen provisioning scheme to prove that their reset/setup work cannot interfere.

```sh
set -o pipefail
CHECKOUT_ENGINE_PGSQL_DATABASE=chkeng_worker_verification \
CHECKOUT_ENGINE_PGSQL_HOST=127.0.0.1 \
CHECKOUT_ENGINE_PGSQL_PORT=5432 \
CHECKOUT_ENGINE_PGSQL_USER=postgres \
./vendor/bin/pest --parallel --processes=3 tests/src/Checkout/Engine \
  2>&1 | tee /tmp/checkout-engine-verification.txt
```

Use only a disposable local test database. Adjust the local connection variables as needed; do not target an application database.

## Prior handoff disposition

| October 19 item | October 20 result |
| --- | --- |
| CHK-08 | Original uncertain-response and verified-redirect-failure recovery examples fixed; durable classification and permanent regressions added. |
| CHK-03 | Frozen processor and concrete default provider fixed at service level. Outer inbound gateway filtering and exact replay inputs remain in CHK-09/CHK-10. |
| CHK-04 | Replacement-before-throw and completed-winner guards fixed. The post-check compensation interval remains unsafe above. |
| CHK-07 | Previous retained-ID/pre-response example fixed; current-generation attribution, obsolete receipts, and metadata transport have permanent regressions. |
| ORD-08 | Closed: order accounting currency is immutable after creation, with normalization and partial-select protection. |
| AZ-06 | Closed: setup/verification failure restoration, nested scope cleanup, and explicit restoration failure behavior implemented. |
| PROD-07 | Closed: default SKU length/collision budgeting and preflight rejection of oversized custom output implemented. |

## Executed verification and limits

| Scope | Current result |
| --- | --- |
| Authz package | 90 passed; 316 assertions. |
| Products package | 528 passed; 1,443 assertions. |
| Signals package | 295 passed; 1,573 assertions; 28 opt-in engine tests skipped. |
| Checkout package | 326 passed; 1,642 assertions; 3 opt-in engine tests skipped, then run separately below. |
| Orders package | 482 passed; 1,536 assertions; 8 opt-in engine tests skipped, then all run on PostgreSQL. |
| Cart backend atomicity | 6 passed; 42 assertions, covering both in-memory implementations. |
| PostgreSQL cart | 12 passed; 308 assertions. |
| PostgreSQL orders | 8 passed; 540 assertions. |
| PostgreSQL checkout, 3 top-level processes | 1 failed, 2 passed; 61 assertions. See TEST-01. |
| PostgreSQL checkout, isolated 1 top-level process | 3 passed; 69 assertions. Each race still creates its cooperating child processes. |
| PHPStan level 6 | Checkout, orders, authz, products, signals, and filament-signals passed. |
| New application reproductions | 4 passed; 20 assertions demonstrating the three remaining application defects. |

All 188 previously reviewed Signals source/config/schema/adapter hashes are unchanged since October 19. Cart, Cashier, CashierChip, support, and affected adapter application files also have no changes in the scoped source snapshot. Their prior full-suite/transport evidence remains historical; full Cart, Cashier, adapter suites and Signals PostgreSQL tests were not all rerun this turn. No new actionable Authz, Products, Orders, Cart, or Signals finding was identified in this review.

The scoped starting snapshot covers 1,145 source/config/migration/resource files, aggregate SHA-256 `ac2f9cd20baed7ecf784f3dc908dd0be3cf01e5bcc152067c5025d8f20a5c11b`. All 1,145 snapshotted application paths remained unchanged. Temporary probes were preserved outside the repository and removed from the test suite; document links and whitespace checks passed. This is a local implementation audit, not a full repository or live-provider certification.

## Implementation sequence and completion checklist

Fix CHK-04 first because it can destroy the identity needed for every other protection. Fix CHK-09 and CHK-10 against one canonical current-attempt contract. Repair engine isolation before using the full parallel PostgreSQL run as the final verification gate.

- [x] CHK-04: fence compensation, JSON/state writes, and failure events through completion of the operation.
- [x] CHK-09: authorize browser and provider inbound traffic against the frozen active attempt.
- [x] CHK-10: replay a complete immutable provider request, preserving nullable fields.
- [x] TEST-01: isolate top-level PostgreSQL test workers while preserving intentional race sharing.
- [x] Add permanent regressions, update canonical docs, and run scoped verification without hiding failures.
