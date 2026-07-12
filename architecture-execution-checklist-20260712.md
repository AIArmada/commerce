# Architecture Execution Checklist — Audited v4

**Verdict:** NO-GO for production refactor. GO only for Wave 0 and Wave 1 design work.

Edit the YAML tracker in Git first; this checklist mirrors it for human review.

## Start authorization
- [ ] GOV-002 resolves the dead repository-rule path.
- [ ] ENV-003 proves Pest and PHPStan can execute.
- [ ] BUG-INV-100 fixes duplicate Inventory commands and durable idempotency.
- [ ] All selected candidate design records are approved.
- [ ] CTR-620 approves compatible cross-package contracts before implementation.
- [ ] CTR-701 verifies actual implementations before Checkout integration.
- [ ] QC-901 produces reproducible green evidence.

## Wave 0

### [ ] GOV-001 — Freeze the audited handoff and register exclusive ownership

- **Status:** `todo`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** none
- **Goal:** Make this YAML the only progress source and prevent two agents from claiming the same task or file.
- **Why:** The prior workboard advertised exclusive ownership while broad textual globs overlapped 21 real files. Lower-capability agents need one simple, diffable claim protocol.
- **Exclusive scope:**
  - `architecture-execution-tracker-20260712.yaml`
  - `architecture-execution-checklist-20260712.md`
- **Acceptance:**
  - [ ] All active agents have unique names and branches/worktrees.
  - [ ] No agent owns more than one source-changing task at the same time.
  - [ ] No two claimed/in-progress/review tasks contain the same exact file path.
  - [ ] Archive SHA-256 is unchanged and recorded as `d39f095b9e76f262bf69dcf140757c94a68cd74d8dad2e36f18a42b305804dfa`.
  - [ ] The tracker contains no wildcard source locks for claimable implementation tasks.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] GOV-002 — Resolve the repository-rule source before any code edit

- **Status:** `todo`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** GOV-001
- **Goal:** Establish which committed instructions are canonical: the missing `.ai/rules` hierarchy referenced by AGENTS.md or the existing `.ai/guidelines` files.
- **Why:** AGENTS.md requires every agent to read `.ai/rules/index.md` before editing, but that path does not exist. Proceeding leaves every later change non-compliant by definition.
- **Exclusive scope:**
  - `AGENTS.md`
  - `.ai/rules/index.md (new only if this hierarchy is selected)`
  - `.ai/guidelines/00-overview.blade.php`
  - `docs/architecture-execution/rule-source-decision.md (new)`
- **Acceptance:**
  - [ ] Every instruction path referenced by AGENTS.md exists.
  - [ ] There is exactly one declared canonical source of repository rules.
  - [ ] The decision record lists observed consumers and rejected alternatives.
  - [ ] No existing rule content is silently dropped.
  - [ ] All later task briefs cite the resolved rule source.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] ENV-003 — Restore a valid Pest and PHPStan baseline

- **Status:** `todo`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** GOV-002
- **Goal:** Make repository verification executable before any refactor begins.
- **Why:** The supplied environment cannot run Pest because DOM is missing and Pest TIA fails during shutdown; PHPStan cannot bootstrap because SQLite support is missing. A plan that requires green tests without a working validator is not executable.
- **Exclusive scope:**
  - `NO REPOSITORY SOURCE EDITS`
  - `/tmp/commerce-audit-baseline/php-environment.txt`
  - `/tmp/commerce-audit-baseline/pest-baseline.txt`
  - `/tmp/commerce-audit-baseline/phpstan-baseline.txt`
- **Acceptance:**
  - [ ] `php -r 'new DOMDocument();'` exits zero.
  - [ ] `php -r 'new PDO("sqlite::memory:");'` exits zero.
  - [ ] `./vendor/bin/pest --version` exits zero without a shutdown fatal.
  - [ ] `php artisan about` boots.
  - [ ] At least one representative package Pest command executes tests rather than failing during bootstrap.
  - [ ] PHPStan reaches analysis and reports code findings or zero errors rather than bootstrap failure.
  - [ ] Every pre-existing failure is recorded as baseline evidence, not silently fixed in this task.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] BUG-INV-100 — Eliminate duplicate Order-to-Inventory commands and make retries harmless

- **Status:** `todo`
- **Severity:** `critical`
- **Owner:** `unassigned`
- **Depends on:** ENV-003
- **Goal:** Guarantee one logical deduction and one logical release per Order operation even when the event is delivered more than once.
- **Why:** Payment confirmation and cancellation each dispatch inventory commands through two independent Orders paths. The second deduction can fall back from missing allocations to direct stock deduction; release can create repeated return movements. This can corrupt stock before any architecture refactor begins.
- **Exclusive scope:**
  - `packages/orders/src/Transitions/PaymentConfirmed.php`
  - `packages/orders/src/Transitions/OrderCanceled.php`
  - `packages/orders/src/OrdersServiceProvider.php`
  - `packages/orders/src/Listeners/DeductInventoryOnPaymentConfirmed.php`
  - `packages/orders/src/Listeners/ReleaseInventoryOnOrderCanceled.php`
  - `packages/inventory/src/Listeners/DeductInventoryFromOrder.php`
  - `packages/inventory/src/Listeners/ReleaseInventoryFromOrder.php`
  - `packages/inventory/src/Models/InventoryOperation.php (new)`
  - `packages/inventory/database/migrations/2026_07_12_000001_create_inventory_operations_table.php (new)`
  - `tests/src/Orders/OrderTransitionsTest.php`
  - `tests/src/Orders/CancelOrderTest.php`
  - `tests/src/Inventory/Feature/OrderInventoryIdempotencyTest.php (new)`
  - `packages/orders/docs/05-state-machine.md`
  - `packages/inventory/docs/04-usage.md`
- **Acceptance:**
  - [ ] One payment confirmation produces one logical inventory deduction.
  - [ ] Two identical InventoryDeductionRequired deliveries produce the same final stock and no duplicate shipment movement.
  - [ ] One cancellation produces one logical inventory release.
  - [ ] Two identical InventoryReleaseRequired deliveries produce the same final stock and no duplicate return movement.
  - [ ] A concurrent duplicate is serialized by a database uniqueness/locking guarantee, not only an in-memory check.
  - [ ] The chosen Orders event path runs after the Order transaction commits.
  - [ ] Tests cover allocation-backed deduction and direct-order deduction.
  - [ ] Tests cover failure after operation creation and safe retry.
  - [ ] Package documentation describes operation identity and retry behavior.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

## Wave 1

### [ ] DES-CHK-110 — Design the deep Checkout workflow and contributor seam

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Choose one coherent Checkout interface and one internal step-assembly seam without exposing caller-visible sequencing.
- **Why:** The current service and pipeline duplicate execution logic, while Events mutates the registry directly. However, the registry is also documented as an extension mechanism, so deleting it without compatibility and long-lived-worker decisions is unsafe.
- **Exclusive scope:**
  - `docs/architecture-execution/design-checkout-workflow.md (new)`
- **Acceptance:**
  - [ ] The chosen external interface does not require callers to coordinate individual step order.
  - [ ] At least two real adapters/contributors justify the internal seam: core Checkout and Events.
  - [ ] Repeated provider boot cannot duplicate or reorder steps.
  - [ ] The migration plan addresses documented custom CheckoutService and registry usage.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-ORD-210 — Design durable Order intake identity and transaction ownership

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Define the Order intake module that materializes an Order once and exposes a retry-safe result.
- **Why:** The existing CreateOrder transaction is local, while Checkout separately stores order_id and confirms payment. Current task scope had no durable intake identity, making concurrent idempotency impossible.
- **Exclusive scope:**
  - `docs/architecture-execution/design-order-intake.md (new)`
- **Acceptance:**
  - [ ] The selected identity is durably unique and concurrent-safe.
  - [ ] The design distinguishes local database atomicity from remote Inventory/Discount commitments.
  - [ ] Exact retry and identity-conflict behavior are explicit.
  - [ ] OrderCreated timing and payload are specified.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-INV-310 — Design reference-centered Inventory reservation and commitment

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Make one Inventory interface own reservation groups, commitment, release, and idempotent observable outcomes.
- **Why:** Checkout receives individual or fake reservation IDs while the implementation allocates by cart/reference and may split across locations. Callers therefore coordinate an implementation detail and can leak allocations.
- **Exclusive scope:**
  - `docs/architecture-execution/design-inventory-commitment.md (new)`
- **Acceptance:**
  - [ ] The external interface never returns fake success identifiers.
  - [ ] One reference can represent all split allocations and observable quantities.
  - [ ] Commit/release retries return stable results.
  - [ ] Ownership between Checkout and Order payment lifecycle is singular.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-OWN-410 — Re-evaluate Owner scope consolidation using the existing deep implementation

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Determine whether a new module is justified or whether package helpers should simply configure and reuse existing commerce-support behavior.
- **Why:** The prior workboard assumed a new OwnerAccessPolicy, but commerce-support already contains OwnerScopeConfig, OwnerQuery, OwnerScope, OwnerWriteGuard, and OwnerContext. A new policy may duplicate rather than deepen.
- **Exclusive scope:**
  - `docs/architecture-execution/design-owner-scope-consolidation.md (new)`
- **Acceptance:**
  - [ ] Recommendation strength is re-evaluated and may legitimately become `rejected` or `no implementation`.
  - [ ] No new module is approved unless the deletion test shows added leverage over existing commerce-support modules.
  - [ ] The exact provider path is correct.
  - [ ] The scope manifest separates common tuple scoping from Inventory-specific relation/cache behavior.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-DSC-510 — Design the combined Promotion and Voucher stacking policy and commitment lifecycle

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Define where cross-system discount composition lives, what the combined cap means, and how provider reservations become durable commitments.
- **Why:** The root architectural gap is not merely untyped data: StackingCoordinationRegistrar is an unreachable stub, the container StackingRuleRegistry is disconnected from StackingEngine, and no enforced cross-system cap exists.
- **Exclusive scope:**
  - `docs/architecture-execution/design-combined-discount-policy.md (new)`
- **Acceptance:**
  - [ ] The chosen seam supports at least Promotion and Voucher adapters without leaking provider vocabulary into Checkout.
  - [ ] The combined cap is defined mathematically, including rounding and currency.
  - [ ] Order independence and package-absence behavior are explicit.
  - [ ] The registrar is either implemented and connected to runtime evaluation or deliberately removed; no dead seam remains.
  - [ ] Reservation, commit, release, and exact retry semantics are specified per provider.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-SHP-610 — Design shipment submission and cancellation as durable remote operations

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** BUG-INV-100
- **Goal:** Represent remote carrier outcomes, unknown outcomes, retries, and reconciliation without lying in local Shipment state.
- **Why:** ShipShipment retries remote creation before durable local evidence; CancelShipment marks local cancellation before the carrier result and ignores a false result. The driver interface collapses unknown, denied, and transient outcomes into bool/error text.
- **Exclusive scope:**
  - `docs/architecture-execution/design-shipment-operations.md (new)`
- **Acceptance:**
  - [ ] No automatic retry can create a second remote shipment without an idempotency/reconciliation decision.
  - [ ] Local Cancelled state cannot be reached when carrier outcome is unknown unless explicitly modeled as a local-only cancellation policy.
  - [ ] The port result distinguishes terminal failure, retryable failure, unknown outcome, already-applied, and success.
  - [ ] J&T adapter migration and generic shipping implementation are separately scoped.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DES-FIN-710 — Design one recoverable Checkout finalization module

- **Status:** `todo`
- **Severity:** `design-gate`
- **Owner:** `unassigned`
- **Depends on:** DES-ORD-210, DES-INV-310, DES-DSC-510
- **Goal:** Make one module own the ordering and durable progress of Order, payment, discount, inventory, completion, and cart cleanup.
- **Why:** CreateOrderStep marks completion and performs voucher/inventory/cart side effects, while CheckoutService also calls FinalizeCheckoutSession. Failures can leave a Completed session with uncommitted inventory or vouchers; free-order finalization errors are only logged.
- **Exclusive scope:**
  - `docs/architecture-execution/design-checkout-finalization.md (new)`
- **Acceptance:**
  - [ ] Completed is impossible before all required commitments reach their accepted terminal state.
  - [ ] A failure leaves a recoverable non-terminal state with recorded failed phase.
  - [ ] Every phase is idempotent or has explicit reconciliation.
  - [ ] There is one finalization implementation and one CheckoutCompleted emission point.
  - [ ] Cart cleanup occurs last and cannot turn an incomplete checkout into apparent success.
  - [ ] Observed facts, inferences, recommendations, and unknowns are labeled separately.
  - [ ] The record names the chosen module, external interface, seam, dependency category, adapters, invariants, errors, transactions, retries, and observable outcomes.
  - [ ] The record contains an exact file manifest with no wildcards before any implementation task is ungated.
  - [ ] The record identifies obsolete modules/tests to delete and compatibility documentation to update.
  - [ ] A reviewer not assigned to the later implementation approves the design.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] CTR-620 — Approve the pre-implementation contract matrix

- **Status:** `todo`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** DES-ORD-210, DES-INV-310, DES-DSC-510, DES-FIN-710
- **Goal:** Prove that Order intake, Inventory commitment, Discount commitment, and Checkout finalization agree before any shared integration code is written.
- **Why:** The prior checkpoint occurred after package implementations and immediately before integration. At that point incompatible identities, money shapes, or transaction assumptions would deadlock the integration lane.
- **Exclusive scope:**
  - `docs/architecture-execution/cross-package-contract-matrix.md (new)`
- **Acceptance:**
  - [ ] All four design records use the same correlation, money, owner, and line concepts.
  - [ ] Every operation has an idempotency identity and observable retry result.
  - [ ] Local DB transactions are not described as atomically covering remote/package-external work.
  - [ ] Operation order and compensation/reconciliation are explicit.
  - [ ] Package-absence outcomes are explicit and never represented by fake IDs.
  - [ ] The integration owner confirms every package result can be consumed without adapter guesswork.
  - [ ] Implementation tasks remain gated until this task is approved.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

## Wave 2

### [ ] CHK-121 — Implement one internal Checkout workflow executor

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-620, DES-CHK-110
- **Goal:** Move all step dependency, validation, state, event, redirect, failure, and rollback coordination into the approved deep module.
- **Why:** CheckoutService and RunCheckoutPipeline currently duplicate the execution algorithm.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Exactly one production method body owns the execution algorithm.
  - [ ] All paths emit equivalent step-completed/failed events once.
  - [ ] Redirect and callback continuation resume at the approved point.
  - [ ] Rollback order and failure state are interface-level tested.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] CHK-122 — Implement deterministic internal Checkout contributors

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CHK-121, DES-CHK-110
- **Goal:** Assemble core and package-provided steps without caller-visible mutable registry coordination.
- **Why:** Events and Checkout currently mutate a singleton registry during provider boot.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Repeated container/provider boot produces the same unique ordered steps.
  - [ ] No normal caller resolves a mutable registry.
  - [ ] Optional packages may contribute or be absent without boot failure.
  - [ ] Order conflicts fail deterministically with a meaningful error.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] EVT-123 — Migrate Events to the approved Checkout contributor seam

- **Status:** `gated`
- **Severity:** `medium`
- **Owner:** `unassigned`
- **Depends on:** CHK-122
- **Goal:** Make Events an adapter at the internal Checkout seam rather than a direct registry mutator.
- **Why:** EventsServiceProvider directly resolves, replaces, and inserts Checkout steps.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Events contributes each enabled step exactly once.
  - [ ] Disabled features contribute no step.
  - [ ] Events boots successfully when Checkout is absent if package independence requires it.
  - [ ] No Events code invokes registry mutation methods.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] ORD-221 — Implement durable, retry-safe Order intake

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-620, DES-ORD-210
- **Goal:** Create or reuse the same Order for the same approved intake identity under concurrency.
- **Why:** The prior Order task promised idempotency without owning a migration/model field capable of enforcing it.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Sequential and concurrent exact retries return the same Order.
  - [ ] Same identity with conflicting immutable data fails explicitly.
  - [ ] Order, lines, addresses, and approved local payment data commit atomically.
  - [ ] OrderCreated is emitted once at the documented time.
  - [ ] No JSON metadata scan is used as the uniqueness authority.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] INV-321 — Implement the approved Inventory reservation-group lifecycle

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-620, DES-INV-310, BUG-INV-100
- **Goal:** Expose reservation, commitment, release, and retry outcomes through one coherent reference-centered interface.
- **Why:** The current integration leaks individual allocation IDs, fake IDs, and ambiguous already-committed state.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] No fake or fallback ID represents success.
  - [ ] All allocations for one reference are committed/released together according to the contract.
  - [ ] Split allocations are covered by tests.
  - [ ] Expired, missing, committed, and released references have distinct documented outcomes.
  - [ ] Concurrent reserve/commit/release tests prove stock invariants.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] OWN-421 — Apply the approved Owner-scope consolidation, only if the design recommends implementation

- **Status:** `gated`
- **Severity:** `medium`
- **Owner:** `unassigned`
- **Depends on:** DES-OWN-410, CTR-620
- **Goal:** Remove duplicated package coordination only where the deletion test proves leverage over existing commerce-support modules.
- **Why:** A preselected new policy would risk another shallow module. This task is conditional and may be marked rejected/skipped.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Task is either explicitly rejected with rationale or completed against an exact manifest.
  - [ ] No new pass-through module is introduced.
  - [ ] Owner-disabled, owner-specific, global-only, and include-global cases remain covered.
  - [ ] Long-lived worker context reset behavior is tested where affected.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DSC-521 — Implement the combined stacking policy and connect runtime rule registration

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-620, DES-DSC-510
- **Goal:** Enforce the approved Promotion + Voucher combined policy in the actual evaluation path.
- **Why:** The registrar is a stub and the bound rule registry is not consumed by StackingEngine.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] A test fails on current main because combined Promotion + Voucher value exceeds the cap, then passes after the change.
  - [ ] Evaluation order does not change the final permitted amount.
  - [ ] The registrar/runtime seam is executable and covered or intentionally deleted.
  - [ ] The container registry is not dead wiring.
  - [ ] Cross-currency or unsupported-currency behavior is explicit.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DSC-522 — Implement Voucher reservation and commitment identity

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** DSC-521, CTR-620
- **Goal:** Make one Checkout reservation/commit/release affect only its own Voucher commitment and make duplicate commit harmless.
- **Why:** Voucher reserve is cache-only, release(code) removes all sessions, and usage persistence lacks a proven Checkout/Order idempotency identity.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Two sessions can reserve the same eligible Voucher according to policy without one release deleting the other.
  - [ ] Exact duplicate commit creates one VoucherUsage.
  - [ ] Conflicting duplicate commit fails explicitly.
  - [ ] Percentage Voucher usage records the actual applied minor-unit amount.
  - [ ] Concurrency tests cover final remaining usage.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] DSC-523 — Implement Promotion commitment from actual Checkout application data

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** DSC-521, CTR-620
- **Goal:** Persist Promotion usage once from the approved commitment result rather than a nonexistent Order field.
- **Why:** MarkPromotionAsUsedOnOrderPlaced reads order.promotion_id and is not registered, so Promotion usage commitment is effectively dead.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] A real Checkout-applied Promotion creates one usage record.
  - [ ] No implementation depends on an unmodeled order.promotion_id attribute.
  - [ ] Duplicate callback/finalization creates no duplicate usage.
  - [ ] Package-absence and deleted/expired Promotion outcomes are documented.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] SHP-621 — Implement durable Shipping operation state and generic carrier outcome semantics

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** DES-SHP-610, CTR-620
- **Goal:** Record submit/cancel intent and outcome before deriving terminal Shipment state.
- **Why:** Current actions can duplicate remote creation or report local cancellation while carrier outcome is false/unknown.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Timeout-after-remote-success scenario cannot create a second shipment blindly.
  - [ ] Unknown cancellation does not appear as confirmed carrier cancellation.
  - [ ] Exact retry returns or reconciles the existing operation.
  - [ ] Concurrent submit/cancel attempts are serialized.
  - [ ] Generic Shipping tests do not depend on J&T-specific payloads.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] JNT-622 — Migrate the J&T adapter to preserve idempotency and uncertainty

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** SHP-621
- **Goal:** Map J&T responses/exceptions into the approved Shipping port without collapsing unknown outcomes to false.
- **Why:** JntShippingDriver catches all errors and returns generic failed/false results, which removes the information needed for safe retry.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Adapter tests cover success, explicit rejection, timeout before send, timeout after possible acceptance, already-created, and cancel unknown.
  - [ ] Unknown outcome remains distinguishable to Shipping core.
  - [ ] No catch-all returns false without structured classification.
  - [ ] Existing rate/label/tracking behavior remains covered.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

## Wave 3

### [ ] CTR-701 — Verify implemented package contracts before Checkout integration

- **Status:** `gated`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** ORD-221, INV-321, DSC-521, DSC-522, DSC-523
- **Goal:** Compare actual code signatures and behavior with the approved pre-implementation matrix before any shared Checkout file is edited.
- **Why:** Even approved designs can drift during implementation. Integration should consume verified contracts, not agent assumptions.
- **Exclusive scope:**
  - `docs/architecture-execution/cross-package-contract-conformance.md (new)`
- **Acceptance:**
  - [ ] Order, Inventory, Promotion, and Voucher actual contracts match the approved matrix.
  - [ ] All shared identities and money types are directly consumable by Checkout adapters.
  - [ ] No integration-specific shape conversion is left unspecified.
  - [ ] All required package commits and migrations are merged.
  - [ ] Exact integration file manifests are committed to the tracker.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

## Wave 4

### [ ] INT-711 — Integrate the verified Inventory contract into Checkout

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-701, CHK-122
- **Goal:** Make ReserveInventoryStep use one real reservation reference and stable outcomes.
- **Why:** Current Checkout adapter returns fake IDs and stores per-item allocation identifiers that do not represent the deep Inventory module.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Split allocations reserve and release correctly.
  - [ ] Retry does not create duplicate reservations.
  - [ ] Absent Inventory is explicit and produces no fake reference.
  - [ ] Rollback after partial failure leaves no leaked allocation.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] INT-712 — Integrate combined Discount evaluation and provider commitments

- **Status:** `gated`
- **Severity:** `high`
- **Owner:** `unassigned`
- **Depends on:** CTR-701, CHK-122
- **Goal:** Make Checkout consume one combined decision while keeping provider commitment adapters behind the seam.
- **Why:** Current ApplyDiscountsStep and adapters evaluate providers separately and cannot enforce one combined cap or durable lifecycle.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] Promotion-only, Voucher-only, both, and absent-package paths follow the matrix.
  - [ ] The combined cap is enforced once and order-independently.
  - [ ] Retries do not create duplicate reservation/commitment.
  - [ ] Checkout never reads provider tables directly.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] INT-713 — Implement one recoverable Checkout finalization coordinator

- **Status:** `gated`
- **Severity:** `critical`
- **Owner:** `unassigned`
- **Depends on:** INT-711, INT-712, ORD-221, DES-FIN-710, CTR-701
- **Goal:** Replace CreateOrderStep's scattered side effects and duplicate finalization with the approved durable phase coordinator.
- **Why:** This is the single shared-file choke point. Starting it before package contracts are verified would force one agent to invent missing semantics and overwrite other lanes.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] No Checkout reaches Completed with an uncommitted required Inventory/Discount operation.
  - [ ] Failure after Order creation resumes without creating a second Order.
  - [ ] Failure after provider commit resumes without duplicate usage/stock movement.
  - [ ] Free-order failure is surfaced, not merely logged.
  - [ ] CheckoutCompleted emits once.
  - [ ] Cart clear runs only after durable completion and is retry-safe.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] CHK-714 — Shrink the public Checkout interface and remove obsolete shallow paths

- **Status:** `gated`
- **Severity:** `medium`
- **Owner:** `unassigned`
- **Depends on:** INT-713, EVT-123
- **Goal:** Complete the depth gain only after all callers use the new workflow and contributors.
- **Why:** Removing public methods earlier would break documented callers and make integration debugging harder.
- **Exclusive scope:** _empty; task is gated and must not be claimed_
- **Acceptance:**
  - [ ] No production caller uses removed methods.
  - [ ] The external interface contains only approved domain operations.
  - [ ] Internal registry/executor types are not documented as public extension points unless intentionally retained.
  - [ ] Obsolete pass-through tests are deleted; behavior remains covered through the interface.
  - [ ] All changed public behavior is documented in the same task.
  - [ ] No out-of-scope file changed.
  - [ ] Package-scoped Pest, PHPStan, and Pint evidence is attached.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

## Wave 5

### [ ] DOC-801 — Verify same-pass documentation and domain language

- **Status:** `gated`
- **Severity:** `medium`
- **Owner:** `unassigned`
- **Depends on:** CHK-714, JNT-622, SHP-621, OWN-421
- **Goal:** Confirm documentation matches implemented interfaces and canonical domain language; do not postpone missing docs to this task.
- **Why:** AGENTS.md requires package docs to be canonical and changed in the same pass as public behavior. This final task is verification, not a dumping ground for deferred documentation.
- **Exclusive scope:**
  - `CONTEXT-MAP.md`
  - `CONTEXT.md`
  - `docs/architecture-execution/final-documentation-audit.md (new)`
- **Acceptance:**
  - [ ] Every public interface change has same-commit package documentation.
  - [ ] No docs describe the removed registry/step execution path unless compatibility remains.
  - [ ] Combined stacking, Inventory idempotency, Shipping uncertainty, and Checkout finalization guarantees are accurately documented.
  - [ ] Canonical terms match CONTEXT files.
  - [ ] No implementation detail is added to glossary definitions.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] QC-901 — Run the complete integrated validation and scope audit

- **Status:** `gated`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** DOC-801
- **Goal:** Prove the merged change set is correct, analyzable, formatted, documented, and free of unauthorized overlap.
- **Why:** Package-level green checks are necessary but not sufficient for a cross-package workflow involving transactions, events, optional packages, and retries.
- **Exclusive scope:**
  - `docs/architecture-execution/final-quality-gate.md (new)`
  - `NO PRODUCTION SOURCE EDITS`
- **Acceptance:**
  - [ ] Tracker validation passes with no active overlap or unscoped changed file.
  - [ ] All required Pest suites pass.
  - [ ] PHPStan passes at repository-required level.
  - [ ] Pint reports no changes.
  - [ ] Package-absence combinations pass.
  - [ ] Critical retry/concurrency scenarios pass.
  - [ ] No environment/bootstrap failure is misreported as a code test result.
  - [ ] Final quality record contains reproducible evidence.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

### [ ] REL-902 — Authorize or reject implementation handoff completion

- **Status:** `gated`
- **Severity:** `blocker`
- **Owner:** `unassigned`
- **Depends on:** QC-901
- **Goal:** Issue one explicit GO/NO-GO decision based on evidence rather than task count.
- **Why:** The work is ready only when critical findings, design decisions, implementation, documentation, and validation all agree.
- **Exclusive scope:**
  - `docs/architecture-execution/release-readiness.md (new)`
  - `architecture-execution-tracker-20260712.yaml`
  - `architecture-execution-checklist-20260712.md`
- **Acceptance:**
  - [ ] No blocker/critical task remains open.
  - [ ] No gated task remains accidentally unreviewed.
  - [ ] Every accepted risk has owner and rationale.
  - [ ] Deployment and rollback order are explicit.
  - [ ] The final decision is GO or NO-GO, not conditional prose.
- **Evidence:**
  - Decision record:
  - Scope manifest:
  - Commit:
  - Tests:
  - Static analysis:
  - Docs:
  - Reviewer:

