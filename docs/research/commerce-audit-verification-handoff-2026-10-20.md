---
title: Commerce audit verification and remaining fixes — 2026-10-20
---

# Commerce audit verification and remaining fixes

Follow-up verification after the [earlier October 20 handoff](commerce-audit-followup-handoff-2026-10-20.md). Reviewed HEAD `05510acc4a7c6debbb73766bfe11433c8893b891` plus all current uncommitted/untracked implementation. Only checkout application files changed since that audit; the other five requested domains and the previously reviewed payment/Filament package source snapshots are unchanged.

**Four actionable findings remain: three P1 and one P2.** The previous callback-routing and PostgreSQL test-isolation findings are closed. Failure-operation ownership and the billable portions of request freezing still have the concrete gaps below. No application source was changed during this review.

## Worker requirements

- No backward compatibility: remove incorrect fallbacks and replace the old contract. Do not add aliases, compatibility shims, legacy branches, or data backfills.
- Edit any required schema change in the owning migration in place. Do not add incremental migrations, database foreign-key constraints, or cascades.
- Read owning package contexts and applicable rules first. Keep optional integrations optional; do not require unified Cashier for native CashierChip.
- Keep provider I/O outside database transactions. Validate ownership for every mutation and irreversible side effect, including recovery on the same generation.
- Add permanent failing regressions for these exact cases, then fix them. Update affected canonical docs and run scoped checks with `--parallel`, `set -o pipefail`, and `2>&1 | tee /tmp/<log>.txt`.
- Use installed provider APIs; verify versions before changing Cashier or SDK integration seams. Run PHPStan level 6 on changed packages and Pint only on changed PHP files.

## 1. CHK-04 — P1: failure authority can be assigned to the wrong execution

**Sources:** [CheckoutService](../../packages/checkout/src/Services/CheckoutService.php):833–855; [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php):536–550, `adopt()`, and `failureOperationOwned()` at lines 691–729; [StepExecutor](../../packages/checkout/src/Services/StepExecutor.php), returned-result handling.

The operation token now protects stale JSON writes when a replacement claim arrives during compensation. Two other ownership intervals remain unsafe.

### A. Old returned failure claims authority over attempt 2

1. Attempt 1 returns a definitive failed `PaymentResult` without a payment ID. Its result is recorded; the payment step emits `CheckoutStepFailed`.
2. Before the old service starts failure handling, another worker claims attempt 2 and records `processing` with ID `pay_replacement_two`.
3. The old `handleCheckoutFailureResult()` calls `beginFailureOperation($session)` without its execution's generation. The manager locks the latest row and substitutes its current generation, 2, for the missing argument.
4. The old flow now has an authoritative-looking attempt-2 failure token. In the reproduced public checkout path it **voids `pay_replacement_two`, marks attempt 2 `PaymentFailed`, and dispatches `CheckoutFailed`**.

The probe uses a step-failed event listener to deterministically stage the separate worker's real `claim()` and `record()` calls. It reproduces the interval before token creation, rather than the already-fixed interval after token creation.

### B. Old token survives successful recovery of the same generation

1. Claim attempt 1 and begin its failure operation after the original execution throws.
2. A recovery worker adopts that unresolved generation and records a completed payment. Its create response is committed, with lifecycle `Processing` while order finalization remains outstanding.
3. The old failure token remains present. `failureOperationOwned()` checks its token and generation but does not reject the new completed outcome in `Processing`.
4. `finishFailureOperationIfOwned()` returns true and marks the checkout failed. The reproduced row retains **a completed attempt outcome and a recovered payment ID beside `PaymentFailed`**.

The second probe exercises the real claim/adoption/record/finish methods; it does not claim that this particular interleaving was run as a PostgreSQL race.

### Fix plan

1. Carry the originating execution/generation through returned failures, validation failures, thrown failures, and callback-driven failures. A missing expected generation must not grant authority over whatever generation happens to be current under the lock. Preserve an explicit pre-claim execution boundary as well.
2. Bind failure authority to the execution/outcome revision it observed, not only the logical attempt number. Adoption or an authoritative result on the same generation must revoke or otherwise supersede an old execution's failure authority before it can compensate or finish.
3. Check the authoritative outcome as part of compensation and finish authorization. Successful or newly owned in-flight outcomes must not be converted into an old failure merely because finalization has not yet reached `Completed`.
4. Apply the same identity to captured compensation resources. Recheck authority before invoking a compensation and when recording its result. A post-side-effect guard alone cannot undo a refund/void or a shared reservation release.
5. Preserve the new fresh-row JSON merge and per-operation compensation evidence. Keep notification decisions tied to the winning operation; emit no failure notification for an obsolete execution.

### Acceptance tests

- Permanent versions of both reproductions above, asserting the replacement payment is never voided/refunded and retains its lifecycle, claim, key, outcome, steps, and error state.
- Returned failure and validation failure after a newer claim; thrown failure followed by same-generation recovery; success committed before order finalization; redirect and terminal outcomes.
- Failure begins before recovery and recovery begins before failure. Assert only the current execution can own the failure operation and that the stale run observes quietly.
- Add PostgreSQL races for the pre-token interval and the same-generation successful-recovery interval. Keep the existing mid-compensation replacement and completed-winner tests.

## 2. CHK-11 — P1: native CashierChip billables fail the new shared resolver

**Sources:** [ResolvesFrozenBillable](../../packages/checkout/src/Support/ResolvesFrozenBillable.php):7, 23–45; [CashierChipProcessor](../../packages/checkout/src/Integrations/Payment/CashierChipProcessor.php):53–63; [native CashierChip billable contract](../../packages/cashier-chip/src/Contracts/BillableContract.php).

### Reproduction

Use a normal native CashierChip billable, such as the package's existing test `User` implementing `AIArmada\CashierChip\Contracts\BillableContract`. Freeze that customer's identity into the checkout request, then call the real `CashierChipProcessor`.

The new shared trait requires **`AIArmada\Cashier\Contracts\BillableContract`**, which is a different interface from native CashierChip's contract. It returns null for the valid native billable; creation returns “Cashier Chip could not resolve the frozen billable customer” before charging.

### Impact

An ordinary supported native CashierChip customer can no longer pay through the built-in processor. The unified Cashier dependency is also optional for this integration, so its interface cannot define the native integration's accepted model boundary.

### Fix plan

1. Separate owner-safe frozen model identity resolution from adapter-specific billing capability checks. Resolve the model once, then let native CashierChip validate its own contract and unified Cashier validate its own contract.
2. Reuse existing shared identity/ownership primitives where appropriate. Do not solve this by making native models implement the unrelated unified interface or by adding a hard package dependency.
3. Keep failure closed when the frozen customer genuinely cannot be resolved. Do not fall back to the current session's customer or turn a billable charge into a guest charge.
4. Preserve an explicitly frozen guest/no-billable mode on replay. Null must not mean “choose a new live billable later.”

### Acceptance tests

- Use an actual native CashierChip billable fixture and its real charge path; assert provider invocation and returned payment identity.
- Boot a minimal native integration setup without the unified Cashier provider; retain optional dependency semantics.
- Test native billable, unified billable, frozen guest, deleted/missing customer, reassociated session, and cross-owner customer. Only the originally authorized frozen identity may be charged.

## 3. CHK-12 — P1: valid Laravel morph aliases are rejected as class names

**Sources:** [PaymentRequestBuilder](../../packages/checkout/src/Support/PaymentRequestBuilder.php), frozen `billableType`; [ResolvesFrozenBillable](../../packages/checkout/src/Support/ResolvesFrozenBillable.php):34–43.

### Reproduction

Register `review20b_billable => ChipBillableUser::class` in Laravel's morph map and associate the session using that alias. Laravel's `session->billable` resolves a valid unified billable. `PaymentRequestBuilder` freezes the alias verbatim. Once the snapshot is present, `CashierProcessor::isAvailable()` returns false because the frozen resolver runs `class_exists('review20b_billable')` instead of honoring the morph map.

### Fix plan

1. Resolve polymorphic identities using Laravel's supported morph mapping APIs. Prefer capturing the authorized concrete model identity when freezing so replay does not reinterpret an alias against a different class later.
2. Keep persisted identity validation and owner scope enforcement after resolving the class. Reject unknown/non-model mappings without falling back to a live session relation.
3. Apply the same resolution contract to availability checks, initial creation, and replay. Avoid a valid model becoming unavailable merely because its request was frozen.
4. Treat legitimate morph-map support as the current domain contract, not a legacy alias path. Do not introduce a second compatibility interpretation of incomplete snapshots.

### Acceptance tests

- Freeze and pay using `Relation::morphMap()` and enforced morph maps with real billable models for both supported adapters.
- Availability, initial creation, and recovery must resolve the same owner-safe model.
- Reject unknown aliases, non-model mappings, missing rows, and foreign-owner rows. Define and test alias remapping after the original claim so it cannot redirect the charge to a different concrete model.

## 4. CHK-10 — P2: provider customer identity still changes beneath a frozen request

**Sources:** [CashierProcessor](../../packages/checkout/src/Integrations/Payment/CashierProcessor.php), `createPayment()`; [ResolvesFrozenBillable](../../packages/checkout/src/Support/ResolvesFrozenBillable.php):43; [StripeGateway](../../packages/cashier/src/Gateways/StripeGateway.php):145–146; [CashierChipProcessor](../../packages/checkout/src/Integrations/Payment/CashierChipProcessor.php):139–159 and its native billable charge path.

### Reproduction

Freeze a Cashier/Stripe request for a billable whose provider customer ID is `cus_frozen`. Send it through the real `CashierProcessor`, real `StripeGateway`, and installed Stripe SDK with a captured HTTP transport. Change that same persisted billable's provider customer ID to `cus_mutated`, then replay the exact frozen request.

The captured outgoing requests contain **`customer=cus_frozen` followed by `customer=cus_mutated`, with identical `Idempotency-Key` headers**. The resolver correctly finds the same local model, but the gateway rereads its mutable `stripeId()` and overwrites the provider request customer. Freezing a local model ID does not freeze its provider identity or provider-derived client fields.

This probe captures actual installed SDK request parameters without contacting Stripe. It proves inconsistent replay, not a live-provider double charge or a particular live API error response.

### Fix plan

1. Freeze the provider-facing customer identifier and all provider-derived creation inputs before the first I/O, in addition to the local billable tuple. Keep explicit guest/billable mode stable.
2. Pass that immutable provider identity through the adapter to the actual SDK call. Do not allow `StripeGateway` or a native billable's `charge()` implementation to overwrite it from the reloaded model during recovery.
3. Inspect the real native CashierChip path too: it currently delegates to the model charge helper, which derives CHIP customer/client details from live model state rather than the request's frozen `client` snapshot.
4. Keep local model loading where needed for authorization, audit, and rate-limit ownership, while ensuring it cannot redefine the frozen external operation. Use existing adapter seams or add the smallest explicit request seam; avoid duplicating charge construction.
5. Preserve exactly the same key and provider payload for recovery. A new customer association or updated provider profile belongs to a genuinely new attempt after definitive failure.

### Acceptance tests

- Capture requests at the actual Stripe SDK transport and CHIP purchase transport. Mutate provider customer ID, email, name, and phone after the initial uncertain attempt; replay must preserve the original provider-relevant payload and key.
- Cover both direct billable charge helpers and unified gateway dispatch. A DTO-only assertion cannot prove this fix.
- Assert null guest identity stays guest, deleted or inaccessible local identities fail according to the explicit contract, and a replacement generation may use updated identity with a new key.
- Retain the already-passing frozen billing/method/callback URL regressions.

## Previous handoff status

| Earlier October 20 item | Verification result |
| --- | --- |
| CHK-04 | Original stale JSON overwrite during replacement compensation fixed. The two ownership cases above still require correction. |
| CHK-09 | Closed for reported browser/notification routing mismatch. Both adapters now use frozen gateway selection and locked revalidation; permanent regressions pass. |
| CHK-10 | Ordinary request fields and nullable method are now snapshotted. Native billable contracts, morph resolution, and actual provider-derived identity remain above. |
| TEST-01 | Closed. Each test owns an isolated PostgreSQL schema inherited by its child race processes; repeated concurrent verification passed. |

## Verification evidence and limitations

- Current full checkout package: **337 passed, 1,710 assertions**, with four opt-in engine tests skipped in the default run and executed explicitly below.
- PHPStan level 6 on checkout: **passed**.
- PostgreSQL checkout engine directory, four top-level processes: **4 passed, 88 assertions**.
- Two additional concurrent invocations against the same disposable database: **each 4 passed, 88 assertions**. Per-test schemas kept their intentionally cooperating child processes connected to the same isolated race state.
- New defect probes: **5 passed, 23 assertions**. Two exercise failure authority; three exercise native billable, morph-map, and Stripe transport behavior. Passing these probes reproduces the defects rather than certifying safety.
- Other requested domains, Cashier/CashierChip application source, support, and reviewed adapters match the preceding audit's application hashes. Their full package suites were not rerun in this verification pass; their prior results remain historical evidence. No new unrelated-domain finding was identified.

The reviewed source/config/migration/resource snapshot contains 1,148 files, aggregate SHA-256 `b3934b39de9e035ad527770787d6f62bd31e6ddaf12baf8272b64280964d3825`. All 1,148 snapshotted application paths remained unchanged. Temporary probes were copied outside the repository and removed from the test suite; link and whitespace checks passed. This is local implementation/SDK-transport verification, not a live-provider certification.

## Worker checklist

- [ ] CHK-04: bind every failure to its originating execution and revoke stale authority on same-generation recovery/results.
- [ ] CHK-11: preserve native CashierChip billable contracts and optional package boundaries.
- [ ] CHK-12: support authorized Laravel morph identities consistently when freezing and resolving billables.
- [ ] CHK-10: freeze provider-facing customer identity through the actual SDK/native charge boundary.
- [ ] Add permanent reproductions, update canonical docs, and run scoped suites, static analysis, and the isolated PostgreSQL engine tests.
