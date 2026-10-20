---
title: Commerce Completed-Implementation Review — Follow-up Handoff
status: fixes-required
reviewed: 2026-10-18
reviewed_head: 05510acc4a7c6debbb73766bfe11433c8893b891
review_surface: current tracked and untracked working tree after completion correction
source_fingerprint: 3c0d3ffda6ebd381d5116988236f220e5073ed83cda8ad4f69a34bab95bcf017
packages: [authz, cart, checkout, orders, products, signals]
---

# Completed-implementation audit: follow-up handoff

## Outcome and scope

**Fourteen actionable findings remain.** This review started from the working tree after the user corrected the earlier completion claim. It supersedes the conclusions from the interrupted partial review. Most previously reported behavior is now corrected; the findings below concern remaining branches, adapter boundaries, and new edge cases.

This is an uncommitted implementation review. HEAD is still the original base, so HEAD alone does not identify the reviewed code. The fingerprint above covers 720 source/configuration/migration/resource files across the six core packages and the products/signals adapters at review start. The application files were not edited during the audit. Audit-only probe tests were removed after retaining local evidence.

Use this document as the active follow-up assignment. Read the [original handoff](commerce-audit-remediation-handoff-2026-10-17.md) and [first follow-up](commerce-audit-followup-handoff-2026-10-18.md) when historical context is needed; their old failure descriptions and verification counts are historical. Preserve the completed implementation and its regression tests.

### Binding implementation constraints

- Implement the corrected contracts directly, with **no backward-compatibility shims, legacy aliases, dual API contracts, or historical-data reinterpretation**. Update all callers affected by a contract change.
- **Edit the owning migrations in place** if schema changes are necessary. Add no incremental schema migrations or backfills. Test fresh schemas only on disposable databases.
- Keep application integrity and cascades in application code. Add no database foreign-key constraints or cascades.
- Preserve owner isolation on every write, including callbacks, refunds, background work, and direct model entry points. UI filters are not authorization.
- Keep amounts in integer minor units with an explicit currency. Do not add implicit currency conversion.
- Keep external gateway I/O outside database transactions while recording claim ownership and outcomes atomically.
- Update canonical package docs with behavior/API/schema changes. A green package suite alone does not close a reproduced edge case.

### What is now verified

| Previous area | Current result |
| --- | --- |
| Scope resolution and configured team switching | Built-in/default/custom switching and restoration work; generic callback exception handling still needs AZ-05 |
| Wildcard authorization | Multiple segments and ordinary mutation freshness work; non-team Super Admin freshness still needs AZ-02 |
| Ambient cart owner isolation and UUID identifiers | Implemented; retain coverage |
| Database cart source transfer | New revision-guarded consumption and PostgreSQL source races pass; memory/backend atomicity still needs CART-07 |
| Cart retry events and automatic empty cleanup | Prior failures corrected with attempt-local results and guarded cleanup |
| Catalog parentage/global-child lineage and attribute boundaries | Corrected; retain domain and adapter coverage |
| Variant identity | Black/Blue separation, membership identity, and repeat generation work; SKU generation edges need PROD-05/06 |
| Order child parent/payment references and partial settlement | Corrected; retain direct-model, owner-disabled, and settlement tests |
| Refund allocation | Default FIFO allocation and remaining-balance limits work; supplied-model freshness, completion replay, and currency boundaries need ORD-05/06/07 |
| Checkout stale replay and basic attempt claim | Sequential completed replay and the existing claim race pass; the six checkout findings below remain |
| Signals privacy, currency reporting, and identity bounds | Original SIG-01/02/03 corrected; 28 PostgreSQL engine cases and report-adapter checks pass |

## Execution order

1. Add permanent failing regressions for the fourteen findings, using the concrete cases below. Audit probes that assert the bug occurs are evidence, not acceptance tests.
2. Fix AZ-02 and the order money boundaries (ORD-05/07); then fix locked refund completion (ORD-06).
3. Complete the checkout attempt boundary in one coordinated pass: CHK-02, CHK-03, CHK-06, CHK-04, CHK-07, then CHK-05. Keep `PaymentAttemptManager` authoritative across processor calls, errors, retries, and callbacks.
4. Correct cart transfer atomicity for every supported backend (CART-07).
5. Finish SKU policy (PROD-05/06) and exception classification (AZ-05).
6. Update callers/docs, run the scoped and real-engine gates, and produce the ID-by-ID completion report.

## Authorization

### AZ-02 — P1: Refresh Super Admin assignments when teams are disabled

**Source:** `packages/authz/src/Support/UserRoleChecker.php:31–35`; the Super Admin Gate hook.

**Reproduced:** Set the permission registrar's `teams` flag to false. Grant Super Admin to a user. Load a second instance of that user and check an arbitrary Gate ability. Revoke the role through the first instance. The first instance reports no role, but the second still authorizes arbitrary abilities because the early non-team branch reads its loaded `roles` collection.

**Required fix:**

1. Make the global-role check read current assignments in both team modes. The existing team-mode refresh must not be bypassed by the non-team early return.
2. Preserve the deliberately documented cross-scope Super Admin meaning. This fix concerns fresh assignments, not changing which scopes the role covers.
3. Restore temporary resolver/team state in `finally`. Do not reintroduce package verdict caches or require callers to clear relationships after revocation.

**Regression gate:** Two loaded instances; revoke/grant/sync through either instance; team mode on/off; a fresh instance; nested scope checks. The first subsequent Gate check must reflect the persisted assignment. No schema change is needed.

### AZ-05 — P2: Propagate policy failures after successful generic scope setup

**Source:** `packages/authz/src/Authz.php:120–130`.

**Reproduced:** Use `DefaultTeamResolver` and a Gate policy that throws `ScopeResolutionException` from inside its callback. `userCanInScope()` returns false because the second catch surrounds the whole generic scope execution. The built-in resolver modes propagate the same callback exception.

**Required fix:** Classify scope resolution/setup rejection before callback execution. Return false only for the helper's own invalid/unresolvable scope rejection. Let policy/callback exceptions propagate unchanged, including an exception whose class happens to match a resolver exception. Preserve prior-team restoration even when the callback throws. Do not broadly catch and reclassify infrastructure errors as authorization denial.

**Regression gate:** A rejected scope never invokes the callback; a successfully scoped callback's exception propagates in every resolver mode; the previous team/override is restored on both paths. No schema change is needed.

## Checkout

The current generation counter and frozen JSON claim are useful foundations. The gap is that adapters, error handlers, and callbacks can bypass the intended certainty/ownership rules. Fix these boundaries together instead of adding independent compensating branches to each controller or processor.

### CHK-02 — P1: Preserve uncertainty when a real gateway call times out

**Source:** `packages/checkout/src/Integrations/Payment/ChipProcessor.php:51–71`; equivalent broad creation catches in `CashierProcessor` and `CashierChipProcessor`; `Services/PaymentAttemptManager.php:57–73`.

**Reproduced:** Use the real `ChipProcessor`, with `Chip::createPurchase()` throwing a connection timeout after the provider could have accepted the purchase. The adapter returns `PaymentResult::failed()`. The manager records a definitive failed outcome. A retry increments the generation and sends a different idempotency key. The unknown-outcome regression using a mock processor that throws does not exercise this real adapter catch.

**Required fix:**

1. Represent outcome certainty explicitly in the processor/result contract. A transport timeout or unclassified exception after a create request is an unknown outcome, not evidence that no charge exists.
2. Keep the original generation and frozen provider identity/key for unknown outcomes. Reconcile/query or safely redrive that identity through the provider's supported idempotency mechanism.
3. Permit a new charge generation only after definitive provider evidence that the prior attempt failed/canceled without an active charge. A local `PaymentFailed` lifecycle state alone is insufficient evidence; remove the shortcut that treats pending/processing plus that local state as a verified failure.
4. Audit every installed built-in creation adapter, including Cashier wrappers that catch exceptions internally. Preserve real provider declines as classified failures.
5. Update payment-flow docs: their timeout-safety claim must hold for real adapters, not only mocks.

**Regression gate:** Simulate provider acceptance followed by a lost response through the actual adapter/HTTP or SDK seam. Recovery uses the same generation/key and produces one provider purchase. Also test definitive decline, pre-send rejection, timeout without known payment ID, and a later successful callback. Add no fresh-charge retry for an unknown outcome.

### CHK-03 — P1: Use the frozen gateway/provider when recovering a claim

**Source:** `packages/checkout/src/Steps/ProcessPaymentStep.php:95–127,308–376`; `Services/PaymentAttemptManager.php` claim/adopt; `Integrations/Payment/CashierProcessor.php:54–57`.

**Reproduced:** Claim generation 1 for gateway `old` and provider `provider-old`. Leave its outcome unknown, then change the session's selected gateway/provider to `new`/`provider-new`. Explicit retry adopts generation 1, but the processor was already resolved from the mutable selection and the request provider is read from mutable `payment_data`. The new gateway/provider is called while the stored claim still says `old` and the generation remains 1.

**Required fix:**

1. Resolve the actual provider for a new attempt before freezing its identity. A multi-gateway adapter must freeze its concrete provider instead of leaving it dependent on a later default.
2. After claim/adoption, resolve the processor from the claim's gateway and route through its frozen provider. Build request/callback metadata from the claim, not from mutable session selections.
3. `CashierProcessor` must honor the frozen requested provider rather than always resolving `GatewayManager::gateway(null)`.
4. Preserve the canonical request fields needed for safe provider replay, including amount, currency, reference, and payment-method identity. Edits to an unresolved attempt must either be rejected or apply only to a later definitively new attempt.
5. Verify status checks, callbacks, and compensation use the same provider namespace. Provider payment IDs alone are not globally unique.

**Regression gate:** Change selected gateway, provider/default gateway, amount, currency, and payment method after an unknown create outcome. Recovery still reaches the original processor/provider with the original payment identity, or rejects the conflicting edit before I/O. A definitively new attempt may use the newly selected method/provider.

### CHK-04 — P1: Fence thrown errors and step writes, not only returned payment results

**Source:** `packages/checkout/src/Services/StepExecutor.php:105–119`; `Services/CheckoutService.php:665–695`; payment exception/compensation paths.

**Reproduced:** While `createPayment()` is in flight, a valid callback completes the session. The processor then throws a network error instead of returning a `PaymentResult`. The step and service catch paths use the old session instance, overwrite the completed lifecycle with `PaymentFailed`, persist an error, and emit `CheckoutFailed`. The stored winning payment remains attached to a failed session. Returned late-failure tests pass because only returned results go through `PaymentAttemptManager::record()`.

**Required fix:**

1. Carry the attempt identity through step begin/result/exception handling. Under the session lock, determine whether that attempt still owns a mutable outcome before changing step state, lifecycle, error fields, or emitting failure events.
2. If a callback/cancel/new generation already won, return the authoritative observed outcome. Do not compensate the winner, reopen its session, or publish a new failure notification.
3. Classify an owning attempt's transport exception using CHK-02. Failure logging may remain, but it must not create false definitive payment evidence.
4. Remove duplicate compensation execution in retry exception handling. Every compensation must identify the attempt/payment it is authorized to unwind and be idempotent.
5. Include the observer's initial `beginStep()` write in the boundary review; an observing process must not rewrite the winner's step map/current-step pointer before it discovers it lacks ownership.

**Regression gate:** Callback success/cancel/new-generation winner followed by a thrown exception at create/record/post-payment boundaries. Assert stored lifecycle, step states, payment ID, errors, exact events, and compensation calls. Add a real-engine race for the stale exception path, not just returned results.

### CHK-05 — P2: Make abandoned-attempt recovery available without spending another attempt

**Source:** `packages/checkout/src/Services/CheckoutService.php:179–219`; checkout state `canRetryPayment()` rules; `PaymentAttemptManager` adoption.

**Two reproduced cases:**

- With retry limit 1, a thrown create timeout leaves generation 1 unknown. `retryPayment()` rejects at its counter limit before reaching adoption, although reconciliation would not increment the counter.
- A hard crash after claim leaves `PaymentProcessing` with no outcome. `retryPayment()` rejects that state outright. The documented crash recovery operation is therefore unavailable unless some separate failure handler happened to run.

**Required fix:** Separate reconciliation of an existing claim from permission to start a new charge. Provide an explicit recovery operation that can query/redrive an unresolved frozen identity, including an abandoned in-flight state and an exhausted new-attempt budget. Keep normal new-charge retry gates and limits. Ordinary duplicate process calls must continue observing an active owner; recovery must use provider reconciliation or the same proven protocol idempotency identity, never another generation merely to escape a stuck state. Update the public service contract and docs directly.

**Regression gate:** Unknown outcome at every counter value, including the maximum; hard crash immediately after claim; active concurrent owner; a definitively failed last attempt; repeated recovery calls; later callback completion. New-attempt counters increase only for genuinely new attempts.

### CHK-06 — P1: Carry the idempotency identity to the provider protocol

**Source:** `packages/checkout/src/Integrations/Payment/CashierProcessor.php:325–342`; `packages/cashier/src/Gateways/ChipGateway.php` and `StripeGateway.php`; `packages/cashier-chip/src/Support/IdempotencyKey.php`.

**Reproduced:** Inspect the real `CashierProcessor` call to the gateway contract. The frozen key exists only as `options.metadata.checkout_idempotency_key`. There is no actual `idempotency_key` charge option. The CHIP billing helper reads the top-level option, so this path does not apply provider idempotency. The direct CHIP and Cashier CHIP adapters' key handling does not protect the generic Cashier path.

**Required fix:**

1. Make the frozen key an explicit processor/gateway operation input and map it to each supported provider's real transport mechanism.
2. For CHIP billing, use the option consumed by its idempotency helper. For Stripe, inspect the installed Cashier/SDK implementation and pass the key as supported request options/header; do not invent a payment-intent parameter called `idempotency_key`.
3. Keep request metadata for attribution only. It is not a substitute for protocol idempotency.
4. Ensure the key and canonical payload remain stable through CHK-02/03 recovery. Fail before a charge if the selected adapter cannot safely reconcile an unknown outcome; document the supported contract.

**Regression gate:** Assert outbound HTTP/SDK request options for every supported gateway. Repeated redrives of one attempt return one provider payment. Two definitively separate attempts use different keys. Include the generic Cashier-to-CHIP and Cashier-to-Stripe paths, not only direct CHIP.

### CHK-07 — P1: Reject callbacks belonging to superseded attempts

**Source:** `packages/checkout/src/Actions/HandleCheckoutPaymentCallback.php`; `Actions/ProcessCheckoutPaymentNotification.php`; `Services/CheckoutService.php:701–793`; callback tokens and gateway references.

**Reproduced:** Attempt A fails locally, then retry creates pending payment B in generation 2. A valid success callback reporting payment A is accepted while B is active. Verification overwrites `payment_id` with A and completes the session. B is neither preserved as the active winner nor reconciled as a superseded charge. The callback path uses session eligibility and amount/currency evidence without matching the current attempt/payment identity.

**Required fix:**

1. Bind each callback/webhook outcome to its immutable generation, gateway/provider, and provider payment identity before any session write or compensation. Verify this against trusted provider evidence under the session lock.
2. When a current payment ID is known, a different payment ID must not silently replace it. Support legitimate callback-before-create-response using the frozen attempt identity, not a blanket session-reference match.
3. Scope redirect tokens to an attempt and rotate them for a new generation. A prior attempt's token/reference must not authorize a newer attempt's transition.
4. Preserve enough attempt attribution to recognize a late valid result. Route a paid superseded attempt to explicit reconciliation/idempotent compensation without overwriting or unwinding the current winner.
5. Apply the same fence to failure/cancel callbacks and to status-poll results. A row lock alone serializes the wrong write if identity is never checked.

**Regression gate:** A success/failure/cancel from generation 1 after generation 2 starts; equal amounts on both; different providers; callback before create response; duplicate callback; old redirect token; correctly attributed current callback. Assert exact payment identity, events, order creation, and compensation targets.

## Orders

### ORD-05 — P1: Reload a specified payment before allocating its capacity

**Source:** `packages/orders/src/Support/RefundAllocator.php:83–101`; specified-payment branches of `Actions/RegisterOrderRefund.php` and `Transitions/RefundProcessed.php`.

**Reproduced:** An order has two completed 5,000 payments. Keep a loaded copy of the first payment, then persist its amount as 2,000 through another instance. The order's paid total correctly becomes 7,000. Pass the stale 5,000 model to `createPending(..., amount: 5000, payment: stale)`. It reserves 5,000 against the payment whose persisted amount is 2,000. The allocator locks the order but trusts the caller's payment fields.

**Required fix:** Resolve the specified payment by its identity through the locked order's owner-safe relationship, then lock/read its authoritative row before using amount, status, currency, or parentage. Reject a missing/deleted/foreign/non-received payment. Treat a supplied model as an identity reference, not a trusted snapshot. Apply this to pending and immediate refunds and retain the existing order → refund/payment lock order consistently.

**Regression gate:** Stale amount/status/currency; deleted payment; unsaved/forged fields; payment on another order/owner; legitimate current model. Actual pending plus completed refunds never exceed the persisted payment capacity. Existing columns suffice.

### ORD-06 — P1: Judge refund idempotency from the locked refund row

**Source:** `packages/orders/src/Transitions/RefundCompleted.php:36–74`; `Models/OrderRefund.php` completion timestamp and provider identity.

**Reproduced:** Two completion workers read a pending refund. One completes it before the other acquires the order lock. The loser checks status on its pre-lock `$refund`, then locks a fresh `$lockedRefund` but never rechecks that row's status. It completes again, emits `OrderRefunded` twice, replaces the first provider refund ID with the second value, and can replace the terminal timestamp. Sequential replay tests miss the interval between the initial read and the lock.

**Required fix:** The initial read may identify the immutable parent only. Acquire the order lock, then the refund lock, and make every completed/failed/pending decision from that locked refund. A completed replay must preserve provider identity and terminal timestamp and emit no event. A failed refund must not become completed through a stale pending snapshot. Keep accounting/event delivery atomic and ensure downstream effects are delivered only for the accepted transition.

**Regression gate:** Two completions with equal/different provider IDs; completion versus failure; already-completed replay; terminal timestamp preservation; exact event count and cached totals. Use deterministic interleaving and a PostgreSQL two-connection race. No schema change is expected.

### ORD-07 — P1: Keep order payment and refund balances in one currency

**Source:** `packages/orders/src/Models/OrderPayment.php` save/aggregate hooks; `Models/Order.php` paid/refundable totals; `Support/RefundAllocator.php`; refund model validation; `tests/src/Orders/RefundPaymentCapacityTest.php` mixed-currency case.

**Reproduced:** A MYR order accepts one 5,000-MYR and one 5,000-USD completed receipt through supported models. Its scalar paid total is 10,000 and `isFullyPaid()` is true. An order-level 7,000 refund request then splits into rows in two currencies whose numeric amounts add to 7,000, despite the API defining no exchange rate or request currency split. Recording currency on each row does not make those scalar sums valid.

**Chosen correction:** Orders use their explicit order currency for payment/refund accounting. Cross-currency settlement is outside this API unless a future explicit conversion model is designed.

**Required fix:**

1. Validate currency consistency on all receipt creation/update/status-completion paths, including direct models and imports, before modifying cached paid totals. A nonzero receipt in another currency cannot count toward the order balance.
2. Require refund currency to match its payment and the order's accounting currency. Shared allocation must operate entirely in that currency.
3. Reject changing a received receipt into another currency through an update. Normalize supported currency codes through the existing money/currency primitives.
4. Replace the new test that treats mixed-currency payments as a valid scalar refund scenario. Preserve the distinct-gateway test using the same currency.
5. Update docs to state the order accounting invariant. Add no implicit conversion, legacy reinterpretation, or backfill.

**Regression gate:** Foreign-currency receipt at creation and on update; pending → completed transition; direct model/action paths; refund currency mismatch; same-currency receipts through different providers; owner isolation. Rejected writes leave rows and cached totals unchanged. Existing currency columns suffice.

## Cart and products

### CART-07 — P2: Make source transfer atomic in the actual storage backend

**Source:** `packages/cart/src/Actions/MigrateGuestCartToUserAction.php:206–255`; `Storage/StorageInterface.php`; `Storage/DatabaseStorage.php`; `Testing/InMemoryStorage.php` and the test harness implementation.

**Reproduced:** Use the package's `Testing\InMemoryStorage`. Guest A has quantity 1. Interleave a successful source addition of B after the source snapshot. The target receives A, source consumption conflicts, and the action retries. `DB::transaction()` cannot roll back the in-memory target write, so the retry merges A again. The final target has A quantity 2 and B quantity 1. Database-backed tests correctly roll back their target write and therefore do not expose this backend mismatch.

**Required fix:**

1. Move the two-cart atomic boundary into a backend-aware transfer capability. It must validate both source/target revisions and commit target data plus source consumption as one unit.
2. Database implementation must use the connection that owns the storage writes; the default facade transaction is insufficient for an independently injected connection.
3. In-memory implementation must stage and atomically validate/apply both sides, preserving successful competing mutations when a transfer loses. A blind snapshot restore must not roll back the competitor's successful write.
4. Update both shipped in-memory implementations and decorators/contracts. Reject incompatible backing stores before any mutation if they cannot participate in one transfer.
5. Preserve the existing PostgreSQL source-race protections and emit one merge event for the committed transfer. Reordering consume/write or adding another default `DB::transaction()` does not solve the boundary.

**Regression gate:** The reproduced source conflict yields A1/B1; target conflict; two consumers; failure after target preparation; separate owner partitions sharing a backend; injected named database connection. No losing transfer leaves target rows, duplicated quantities, metadata, or tombstone attribution behind. No schema change is expected.

### PROD-05 — P2: Make default SKU encoding unambiguous across option boundaries

**Source:** `packages/products/src/Strategies/MatrixVariantGenerator.php:113–129,426`; option-code generation and default configuration.

**Reproduced:** Option A values `AB` and `AB-CD` produce codes `AB` and `AB-`. Option B values `-C` and `C` produce codes `-C` and `C`. Although each option's codes are individually distinct, joining them with `-` makes two combinations produce `AB--C`. Default generation throws duplicate-SKU validation instead of producing four valid variants.

**Required fix:** Default SKU generation must include deterministic combination identity or another bounded, unambiguous encoding across all options. A stable suffix derived from the canonical combination key is acceptable; mutable names remain display data. Keep length/owner uniqueness constraints and rerun stability. Treat default disambiguation independently from custom-pattern policy in PROD-06.

**Regression gate:** All four combinations above; punctuation/delimiter boundaries; equal/short codes; option order and label edits; repeated and concurrent generation. Assert four distinct correct memberships and stable regenerated identifiers. Reuse the new `combination_key`; no additional schema is expected.

### PROD-06 — P2: Fail explicitly when a custom SKU pattern collides with an existing record

**Source:** `packages/products/src/Strategies/MatrixVariantGenerator.php:194,225–245`; generation transaction scope.

**Reproduced:** Configure `{parent_sku}-FIXED`. Add an unrelated manual variant occupying that SKU on the product. Generating the one option combination silently creates `...-FIXED-<hash>` rather than reporting the custom-pattern collision. This contradicts the specified explicit custom-pattern failure contract.

**Required fix:** Separate opinionated default SKU disambiguation from a custom pattern. Reuse an existing variant only for the same canonical membership. A custom candidate occupied by another membership/record must fail before modifying the generation result. Validate collisions against existing rows as well as combinations in the current batch. Ensure all-or-nothing behavior across batch boundaries, including more than 50 combinations, rather than committing earlier chunks before a later collision.

**Regression gate:** Occupied custom SKU on the same product and a sibling product; matching-membership replay; two generated combinations collapsing; collision after the first chunk; concurrent generation. No silently invented custom SKU and no partial generation on failure. No new schema is expected.

## Verification and completion

### Current-review evidence

Fifteen focused observed-defect tests reproduced the fourteen findings. CHK-05 has two probes (last-attempt timeout and abandoned claim); each other finding has one. These tests intentionally assert the current bad behavior. Create permanent regressions asserting the corrected behavior; do not mistake the audit probes' green result for acceptance.

| Current review check | Result |
| --- | --- |
| Authz package | 62 passed |
| Cart package | 1,094 passed; 14 skipped in the default run |
| Checkout package | 280 passed; 1 engine case skipped in the default run |
| Orders package | 456 passed; 7 engine cases skipped in the default run |
| Products package | 510 passed |
| Signals package | 295 passed; 28 engine cases skipped in the default run |
| PostgreSQL cart races | 12 passed, 308 assertions |
| PostgreSQL checkout race | 1 passed, 36 assertions |
| PostgreSQL order races | 7 passed, 480 assertions |
| PostgreSQL Signals races | 28 passed, 713 assertions |
| Targeted FilamentCart adapters | 26 passed |
| FilamentProducts relationship boundaries | 2 passed |
| FilamentSignals suite | 37 passed |
| PHPStan level 6 | Six core packages and `filament-signals` passed with existing configuration |
| Observed-defect probes | 15 passed, demonstrating the 14 remaining findings |

The six current package runs total **2,697 passed**. All **48 PostgreSQL engine cases passed** separately with the disposable databases configured. The two opt-in cart stress cases remain unrun; all other default-run skips above were covered by those engine runs. Adapter checks add **65 passed**. These green suites do not cover the bad behavior demonstrated by the focused probes.

The first Products invocation lost an audit-only file during interrupted-probe cleanup and did not produce a valid package result. The clean rerun after cleanup passed 510 tests; only that result is counted. Logs are local `/tmp/commerce-finalaudit-*.txt`; the Products result is `commerce-finalaudit-products-suite-clean.txt`. Recreate the described regressions permanently instead of relying on temporary probe copies or logs surviving on the review machine.

### Required verification for the worker

- Run focused regressions red/green, then each affected package directory separately. Every Pest invocation includes `--parallel` and `2>&1 | tee /tmp/<specific-log>.txt`; enable `set -o pipefail`.
- Run scoped PHPStan level 6 with existing configuration/ignore lists unchanged. `--debug` is acceptable if local parallel sockets are blocked. Run Pint only on changed PHP files/packages.
- Run real PostgreSQL claim/callback/exception, refund completion, and transfer races on dedicated disposable databases. Keep the accepted `chkeng_`, `ordeng_`, `carteng_`, and `sigeng_` prefixes. Existing engine assertions do not cover all new findings.
- Verify actual outbound gateway HTTP/SDK idempotency and frozen routing. A mock processor that merely echoes request metadata cannot prove provider safety.
- Recheck the completed Signals and owner-isolation contracts when changing shared payment events, currencies, or storage interfaces. Run relevant Filament adapters and all in-repository callers of changed service/refund/processor contracts.
- Validate edited migrations on fresh disposable schemas; retain configurable JSON types and application-level integrity. Make no changes to an application database.

Example scoped verification:

```sh
set -o pipefail
./vendor/bin/pest --parallel tests/src/Checkout 2>&1 | tee /tmp/worker-checkout-tests.txt
./vendor/bin/phpstan analyse packages/checkout/src --level=6 --no-progress --memory-limit=1G 2>&1 | tee /tmp/worker-checkout-phpstan.txt
```

Configure each engine suite's `*_ENGINE_PGSQL_HOST`, `*_PORT`, `*_USER`, and `*_DATABASE` for the local disposable environment. Do not commit machine-specific launcher files, connection values, or credentials.

### Schema ownership if needed

The findings should mostly use existing identity/currency columns and corrected contracts. If additional persisted attempt attribution is required, edit `packages/checkout/database/migrations/2024_01_01_000001_create_checkout_sessions_table.php` in place. Refund schema belongs to `packages/orders/database/migrations/2000_11_01_000005_create_order_refunds_table.php`; variant identity belongs to `packages/products/database/migrations/2001_01_01_000004_create_product_variants_table.php`. Start new fields clean; add no compatibility interpretation of historical nulls.

### Delivery checklist

- [x] AZ-02: non-team Super Admin revocation is immediately effective.
- [x] AZ-05: scoped policy exceptions propagate with context restored.
- [x] CHK-02: real-adapter transport uncertainty preserves the attempt identity.
- [x] CHK-03: recovery uses the frozen gateway/provider/request identity.
- [x] CHK-04: thrown errors and step writes cannot overwrite a winning flow.
- [x] CHK-05: abandoned/last-budget attempts can reconcile without new charges.
- [x] CHK-06: every supported provider receives actual protocol idempotency.
- [x] CHK-07: superseded callbacks cannot mutate the current attempt.
- [x] ORD-05: specified payment capacity comes from its authoritative locked row.
- [x] ORD-06: concurrent refund completion is idempotent and preserves terminal facts.
- [x] ORD-07: order accounting never sums mixed-currency receipts/refunds.
- [x] CART-07: transfer rollback/atomicity holds for every supported backend.
- [x] PROD-05: default SKUs remain distinct across option-code boundaries.
- [x] PROD-06: custom-pattern collisions fail explicitly and atomically.
- [x] Previously completed fixes and canonical docs remain correct.
- [x] Changed contracts have updated callers, permanent regressions, and scoped verification.
- [x] Any necessary schema edits are in owning migrations; no compatibility/backfill path was added.

The final worker report must map every ID to source changes and permanent tests, list actual test/static-analysis/engine results, and identify any unverified boundary. A checked handoff item requires its completion gate, not only the presence of a class or a passing broad suite.
