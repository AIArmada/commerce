# Independent Architecture Handoff Audit

- **Repository:** `aiarmada/commerce`
- **Archive:** `commerce(3).zip`
- **SHA-256:** `d39f095b9e76f262bf69dcf140757c94a68cd74d8dad2e36f18a42b305804dfa`
- **Audit date:** 2026-07-12
- **Verdict:** **NO-GO for production refactor. GO only for Wave 0 and Wave 1 design work.**

## Scope and method

This audit independently re-read the repository instructions and context files, traced each architectural candidate through production callers, events, tests, migrations, package documentation, and optional-package seams, validated the prior YAML dependency graph and file scopes, and attempted the required test/static-analysis baseline. Facts, inferences, recommendations, and unknowns are kept separate in the revised workboard. This certifies the architecture handoff and the code paths it proposes to change; it is not a line-by-line defect audit of unrelated packages in the monorepo.

## Release blockers

### F-01 — BLOCKER: Verification environment is not executable

**Observed facts**
- Pest fails before test discovery because DOMDocument is unavailable.
- Pest then throws an unresolved TIA State dependency during shutdown.
- PHPStan cannot bootstrap Laravel because PDO SQLite is unavailable.
- Composer is not available in PATH.

**Impact:** No agent can prove a refactor correct. Any `tests pass` claim would be unsupported.

**Required correction:** ENV-003 is a hard gate before source work.

### F-02 — BLOCKER: Repository instructions point to a missing rule hierarchy

**Observed facts**
- AGENTS.md:570 requires `.ai/rules/index.md`.
- Only `.ai/guidelines/*.blade.php` exists.

**Impact:** Every source edit would violate the repository's own mandatory preparation rule.

**Required correction:** GOV-002 resolves and records one canonical rule source.

### F-03 — CRITICAL: Order payment and cancellation can command Inventory twice

**Observed facts**
- PaymentConfirmed emits InventoryDeductionRequired directly and OrderProcessingStarted after commit.
- OrdersServiceProvider listens to OrderProcessingStarted and emits the same Inventory command through another listener.
- OrderCanceled and OrderCancelInitiated have the same duplicate release shape.
- The second deduction may find allocations gone and perform direct stock deduction; repeated release can add repeated return movements.

**Impact:** Stock can be double-deducted or double-restored.

**Required correction:** BUG-INV-100 precedes all architecture candidates and adds durable operation idempotency.

### F-04 — HIGH: The prior plan skipped required design confirmation

**Observed facts**
- The supplied architecture method requires candidate selection, one-decision-at-a-time stress test, alternative interfaces, and user confirmation before implementation.

**Impact:** Detailed instructions could accelerate an unapproved or internally inconsistent architecture.

**Required correction:** Seven explicit design records plus CTR-620 now gate all implementation.

### F-05 — HIGH: C05 was still solution-biased after acknowledging the stacking stub

**Observed facts**
- StackingCoordinationRegistrar is a comment-only stub and not invoked by PromotionsServiceProvider.
- The container StackingRuleRegistry is disconnected from StackingEngine runtime rules.
- Voucher reservation/release and Promotion usage commitment have separate correctness defects.

**Impact:** Filling the stub inside Promotions could place the seam in the wrong context and leave provider commitment broken.

**Required correction:** DES-DSC-510 decides ownership/cap/lifecycle; DSC-521, DSC-522, and DSC-523 are separate.

### F-06 — HIGH: The contract checkpoint was too late

**Observed facts**
- Package implementations were allowed to invent Order, Inventory, and Discount contracts independently before CTR-601.

**Impact:** The shared Checkout lane could deadlock on incompatible identities, money shapes, or transactions.

**Required correction:** CTR-620 is pre-implementation; CTR-701 checks actual conformance before integration.

### F-07 — HIGH: Checkout finalization is a missing candidate

**Observed facts**
- CreateOrderStep marks completion and performs voucher/inventory/cart side effects.
- CheckoutService separately calls FinalizeCheckoutSession.
- Free-order finalization errors are logged without failing the successful path.

**Impact:** A Checkout can appear Completed while required commitments failed, and retries can duplicate side effects.

**Required correction:** Candidate 07 and INT-713 create one recoverable finalization module.

### F-08 — HIGH: Order intake idempotency was impossible within the old scope

**Observed facts**
- The old ORD-201 did not own an Order migration/model field for durable unique intake identity.

**Impact:** Concurrent retries could create duplicate Orders despite typed data objects.

**Required correction:** DES-ORD-210 defines identity; ORD-221 must own durable uniqueness.

### F-09 — HIGH: Shipping actions lose remote uncertainty

**Observed facts**
- ShipShipment retries create before persisting operation evidence.
- CancelShipment transitions local Cancelled before carrier call and ignores false.
- The interface uses bool cancellation; J&T catch-all maps unknown outcomes to false.

**Impact:** Duplicate remote shipments and false local cancellation are possible.

**Required correction:** DES-SHP-610, SHP-621, and JNT-622 model durable operation outcomes.

### F-10 — MEDIUM: Owner consolidation was overstated and referenced a nonexistent provider

**Observed facts**
- Actual provider is `packages/commerce-support/src/SupportServiceProvider.php`.
- Existing OwnerScopeConfig, OwnerQuery, OwnerScope, and OwnerWriteGuard already hold common behavior.

**Impact:** A new OwnerAccessPolicy could be another shallow module and broad scopes would overlap other tasks.

**Required correction:** Candidate downgraded to Worth exploring; implementation is conditional on deletion-test evidence.

### F-11 — MEDIUM: The old tracker was not truly exclusive

**Observed facts**
- Broad globs and parenthetical exclusions expanded to at least 21 overlapping real files.

**Impact:** Multiple agents could overwrite shared providers, helpers, adapters, and tests.

**Required correction:** Claimable tasks require exact path manifests; gated tasks start with no source scope.

### F-12 — MEDIUM: Documentation was deferred contrary to repository rules

**Observed facts**
- Old source tasks omitted many canonical package docs and relied on a final documentation wave.

**Impact:** Public behavior and docs could diverge across commits.

**Required correction:** Every implementation task now owns same-pass docs; DOC-801 only verifies.

## Candidate reassessment

| Candidate | Architectural move | Audited strength | Disposition |
|---|---|---|---|
| C01 | Hide Checkout step graph | Strong, design required | Evidence remains valid; public compatibility and contributor lifetime must be decided first. |
| C02 | Deep Order intake | Strong, design required | Needs durable intake identity and explicit local transaction/event semantics. |
| C03 | Inventory commitment | Critical + Strong | Includes immediate duplicate-command corruption plus deeper reservation-interface friction. |
| C04 | Owner scope consolidation | Worth exploring | Existing commerce-support depth may already solve the common behavior; implementation may be rejected. |
| C05 | Combined Promotion/Voucher policy | Strong, design unresolved | Root gap is dead/unreachable stacking seam plus provider commitment defects. |
| C06 | Shipment operations | Strong, design required | Remote unknown outcomes and idempotency require durable operation state. |
| C07 | Checkout finalization | Strong — newly added | Completion is duplicated and ordered before required commitments. |

## Prior task-by-task disposition

| Old task | Old title | Disposition | Audit reason |
|---|---|---|---|
| GOV-001 | Register agents, branches, and exclusive task ownership | replace | Retained but simplified to exact YAML/Markdown ownership. |
| GOV-002 | Capture baseline tests and repository-rule discrepancy | split | Mixed environment baseline and rule inconsistency; now GOV-002 + ENV-003. |
| CHK-101 | Create the single internal Checkout workflow executor | gate | Valid friction, but implementation was prescribed before interface/design decisions. |
| CHK-102 | Internalize step registration behind a contributor seam | gate | Valid seam, but registry compatibility and long-lived-worker lifetime were unresolved. |
| EVT-103 | Migrate Events to the Checkout contributor seam | retain-after-design | Real second adapter; now depends on approved contributor seam. |
| ORD-201 | Build the typed, atomic Order intake module | replace | Could not satisfy concurrent idempotency without migration/model ownership. |
| INV-301 | Deepen Inventory checkout allocation lifecycle | replace-critical | Missed duplicate command corruption and leaked/fake reservation identities. |
| OWN-401 | Add the shared instance-based Owner access policy | reject-as-written | Referenced nonexistent provider and duplicated existing Owner modules. |
| OWN-411 | Migrate Pricing and Promotions to shared Owner policy | gate | Broad package call-site glob was not exclusive; implementation now conditional. |
| OWN-412 | Migrate Shipping and Tax to shared Owner policy | gate | Overlapped Shipping work and assumed solution before deletion test. |
| OWN-413 | Migrate Inventory owner helpers to shared policy | gate | Inventory-specific relationship/cache behavior was incorrectly treated as generic. |
| DSC-501 | Implement the combined Promotion + Voucher stacking policy | replace | Root cause corrected, but ownership/cap semantics must be designed first. |
| DSC-502 | Build provider-side discount commitment operations | split | Voucher and Promotion commitment defects differ; now DSC-522 + DSC-523. |
| SHP-601 | Deepen Shipment execution and explicit remote uncertainty | split-and-gate | Too broad; generic operation state and J&T adapter are separate. |
| CTR-601 | Verify Order, Inventory, and Discount contracts before Checkout integration | move-earlier-and-duplicate | Pre-implementation CTR-620 plus post-implementation CTR-701. |
| INT-701 | Integrate typed Order intake in CreateOrderStep | gate | Cannot start until the actual Order intake contract and cross-package matrix are verified. |
| INT-702 | Integrate the reference-centered Inventory lifecycle into Checkout | gate | Cannot start until the actual Inventory reservation/commitment contract is verified. |
| INT-703 | Integrate typed Discount decision and commitment into Checkout | replace | Discount integration remains gated, and the shared CreateOrderStep work moves to a separate critical finalization coordinator. |
| DOC-801 | Synchronize canonical package documentation and migration notes | retain-as-audit | Docs must be same-pass; final task only verifies. |
| QC-901 | Run integrated verification and reject incomplete deepening | retain-strengthened | Now prohibits source patches and requires integrated retry/package-absence evidence. |

## Start authorization

Agents may start only these task classes:

- `GOV-001`, `GOV-002`, and `ENV-003`
- `BUG-INV-100` after the environment gate
- Wave 1 design tasks after the critical bug task

No Wave 2–5 source task may be claimed until its design, dependency, and exact-scope gates are satisfied.

## Validation limitation

The audit could not produce a green code baseline in the supplied runtime. Pest failed before test execution because DOM/XML is missing and then failed in Pest TIA shutdown; PHPStan failed to bootstrap because SQLite/PDO_SQLite is missing. These are environment findings, not proof that repository tests or analysis fail.
