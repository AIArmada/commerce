---
title: Checkout execution revision fence — 2026-10-20
---

# Checkout execution revision fence

Verification after the [preceding handoff](commerce-audit-verification-handoff-2026-10-20.md). Reviewed HEAD `05510acc4a7c6debbb73766bfe11433c8893b891` plus the current working tree. Checkout, Cashier, and CashierChip contain the new fixes; the other five requested core domains and reviewed adapters are unchanged in the application snapshot.

**CHK-13 is closed.** `PaymentAttemptManager::record()` now requires the exact claim revision as well as the generation, and step-state writes, validation failures, thrown failures, and failure events use the execution's captured origin. Accepted payment results carry their committed origin into the step write. Same-generation recovery cannot be overwritten by an older response, and a stale throw remains an observation. Deterministic regressions and both PostgreSQL race windows pass.

## CHK-13 — P1: a stale execution on the same generation can still overwrite recovery (closed)

### Sources

- [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php):828–873, `record()` checks generation and the claim revision under the session row lock.
- [PaymentAttemptManager](../../packages/checkout/src/Services/PaymentAttemptManager.php):951–1025, step-state and failure writes require the full execution origin.
- [StepExecutor](../../packages/checkout/src/Services/StepExecutor.php):226–266, `recordThrownStep()` preserves the captured origin for the failed-step write and event.
- [PaymentFailureOrigin](../../packages/checkout/src/Data/PaymentFailureOrigin.php) and [PaymentAttemptClaim](../../packages/checkout/src/Data/PaymentAttemptClaim.php) already carry the information needed for an authoritative check.

### Reproduction A: a late uncertain result erases a paid recovery

1. Original execution A claims attempt 1, revision 1, and is still waiting on gateway I/O.
2. Another request explicitly recovers that attempt. Adoption keeps generation 1 and its idempotency key, while advancing the execution revision.
3. Recovery B records a completed payment, `pay_recovered_paid`. Its result is committed and the session is `Processing` while order finalization is still outstanding.
4. A's original request times out and returns the built-in uncertain result shape: processing/uncertain with no payment ID.
5. `record($session, $oldClaim, $uncertainResult)` accepts it because the generation is still 1 and lifecycle is `Processing`.

**Pre-fix verified state:** the paid recovery ID became null, the completed outcome became processing/uncertain, and the winning gateway evidence was replaced. The stale claim's revision was lower than the recovered claim's revision, but that fact was not consulted.

The deterministic probe uses the real claim, adoption, and record methods. It verifies the internal overwrite; it does not assert a second live charge. Provider idempotency does not protect the application's saved payment state from this overwrite. Recovery may overlap a slow original request because the process guard is request-scoped.

### Reproduction B: stale throw writes a failed step after adoption

1. A claims attempt 1 and enters its gateway call.
2. B adopts the same generation and remains in `PaymentProcessing` before its create response is recorded.
3. A throws. Its exception correctly contains the original revision.
4. `recordThrownStep()` passes only attempt number 1 to the manager. The manager sees the adopted generation is still reconcilable and marks `process_payment` failed.

**Pre-fix verified result:** the replacement remained `PaymentProcessing`, but its payment step was failed and a stale `CheckoutStepFailed` event was dispatched. The later failure-operation check correctly rejected the old origin and did not dispatch `CheckoutFailed`; that later check could not undo the earlier step mutation/event.

### Implemented correction

1. **Fenced creation-result recording by the full execution identity.** The locked write compares generation, revision, and lifecycle before persisting an outcome, payment ID, evidence, or status change.
2. **Kept result revisions coherent.** Accepted results advance the revision and return the committed origin for the next guarded step. Stale results leave the current revision and evidence untouched.
3. **Carried the same origin through step and failure writes.** Validation, `canSkip`, dependency checks, thrown exceptions, failed-step events, and failure operations retain the origin captured before that work began.
4. **Guarded every later step-state write.** Begin, skipped, completed, and failed writes use the captured or accepted result origin; stale work cannot gain authority by rereading the latest revision.
5. **Preserved revision-aware failure operations.** Adoption and authoritative outcomes invalidate old operation authority, and the earlier result/step boundaries now follow the same revision contract.
6. **Kept gateway I/O outside transactions.** Only short row-lock checks and writes run inside the transaction.
7. **Preserved payment-specific orphan handling.** A rejected old result may void its own distinct orphaned payment while retaining the winner's payment; an old response matching the winner is never voided.

### Permanent regression coverage

- [x] Reproduced A through real `PaymentAttemptManager` methods, rejecting the old uncertain result while preserving the recovery ID, evidence, provider, amount/currency, revision, and step state.
- [x] Repeated with stale failed, pending, processing, and successful results, covering both matching and distinct payment IDs.
- [x] Exercised public processing/recovery flows with overlapping executions; the built-in stale result becomes quiet observation.
- [x] Reproduced B with adoption paused before its result; no stale failed-step write, failure event, checkout failure, or compensation occurs.
- [x] Covered a same-generation outcome arriving before the step write, validation/returned failures, pre-claim `canSkip` and payment preflight throws, and a replacement claim during a thrown-step event.
- [x] Preserved normal still-owning success/failure behavior and rejection of older generations.
- [x] Added PostgreSQL races for both precise intervals, including adoption while the outcome remains unresolved.

## Implementation constraints

- No backward compatibility: change the internal APIs and update callers/tests together. Do not retain the attempt-number-only path or add legacy fallback interpretations.
- Use the existing attempt revision/origin primitives. Keep the correction within checkout unless a demonstrated shared boundary requires otherwise.
- If schema changes prove necessary, edit the owning migration in place; do not add incremental migrations, backfills, foreign-key constraints, or cascades. The existing JSON revision already stores the needed authority.
- Keep owner enforcement on all reads and writes. Read the package context and applicable rules before editing.
- Update canonical checkout docs for the complete execution fence. Run Pint only on changed PHP files, scoped PHPStan level 6, and scoped Pest with `--parallel` and output piped through `2>&1 | tee /tmp/<log>.txt` under `set -o pipefail`.

## Prior handoff disposition

| Item | Current result |
| --- | --- |
| CHK-04 prior returned-failure and failure-token examples | Fixed by captured origins/revisions and operation invalidation. CHK-13 now closes the earlier creation-result and step-write boundaries as well. |
| CHK-11 native CashierChip contract | Closed; adapter-specific native/unified capability checks pass. |
| CHK-12 morph aliases | Closed; aliases resolve correctly and new snapshots retain concrete model identity. |
| CHK-10 actual provider identity replay | Closed for the reported Stripe/native CHIP cases; frozen provider IDs, explicit nulls, and client fields reach the transport. |
| Previous routing/test-isolation fixes | Preserved. Current PostgreSQL engine suite passes with isolated schemas. |

## Verification evidence

- Checkout package: **368 passed, 1,964 assertions**; eight opt-in engine tests skipped in that run and all eight passed on PostgreSQL.
- Cashier package: **249 passed, 584 assertions**.
- CashierChip package: **561 passed, 1,077 assertions**.
- PostgreSQL checkout engine: **8 passed, 151 assertions**, four top-level parallel processes. Both new overlap windows passed.
- PHPStan level 6: checkout passed with `--debug --no-progress`.
- Focused checkout regressions: revision fence **10 passed, 115 assertions**; thrown/validation fence **7 passed, 63 assertions**; `StepExecutorTest` **6 passed, 14 assertions**; updated redirect and orphan cases **1 passed, 3 assertions** and **1 passed, 6 assertions**. These overlap the full checkout suite and are not additional distinct coverage counts.
- Focused existing billable regressions: **12 passed, 61 assertions**; real Stripe transport regressions: **4 passed, 21 assertions**. These overlap the full package suites and are not additional distinct coverage counts.

This follow-up changes checkout implementation and tests only. Other core domains and adapters match the previous review's snapshot; their full suites were not rerun. The earlier 1,149-file snapshot and aggregate SHA-256 `2df58c4f4680825b8c7e8c519afdfe3190a1cfa5ce0fb7904b50bdb1a6f12b60` are historical baseline evidence from before this follow-up. Document links and whitespace checks passed.

## Worker checklist

- [x] Compare execution revision as well as generation before recording a creation result.
- [x] Carry the full origin through failed and subsequent step-state writes/events.
- [x] Add permanent deterministic and PostgreSQL regressions for both overlapping-execution intervals.
- [x] Preserve provider/billable, uncertainty recovery, routing, compensation, and owner-isolation regressions.
- [x] Update checkout docs and complete scoped verification.
