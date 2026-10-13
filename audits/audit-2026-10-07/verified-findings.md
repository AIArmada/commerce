# Full-Application Audit — Verified Findings (2026-10-07)

**Current status (2026-10-10): four confirmed findings resolved; F-03 refuted and
verified clean. No confirmed code findings remain open in this document.** The
original evidence and proposed plans below are historical; the review and
[resolution](#resolution-muse-code-implementation-2026-10-10) record the final disposition.
MariaDB runtime verification passed; live MySQL verification remains a release check.

- **Scope:** entire commerce monorepo (69 packages + root `tests/`), focused on bugs, security, performance.
- **Pinned to:** `e78d3fc3c` (main) + the uncommitted working-tree changes reviewed as part of the audit.
- **Method:** every finding below is re-verified against source before recording (file:line evidence, callers
  checked, prior audit claims cross-checked). Candidate findings that failed re-verification are recorded in
  **Verified clean** with the reason, so they are not re-raised. This file is the running record; later sweeps
  append new finding IDs.
- **Relation to prior audits:** `audits/OPEN-ISSUES.md` (Aug–Sep 2026, 66 items), `audits/README.md` (Sept sweep),
  `audits/share-dawah-*-2026-10-06.md`. This audit does not re-report items already tracked there unless a new
  verified aspect emerged.

## Severity rubric

| Severity | Meaning |
|---|---|
| Critical | Breach-class security hole, silent data loss/corruption, broken money integrity. |
| High | Documented contract unenforced; revenue-affecting work fails or double-applies. |
| Medium | Real reliability/correctness bug with bounded blast radius; broken race path. |
| Low | Hygiene, latent trap, doc/behaviour mismatch. Safe to defer. |

---

## Findings

### F-01 (Medium) — `RecordAffiliateOutcome` concurrency retry breaks after the conversion row is saved (phantom-model update)

**Status:** resolved (2026-10-10; post-save retry regression passes for allowed and rejected outcomes).
**Location:** `packages/affiliates/src/Actions/Conversions/RecordAffiliateOutcome.php:169-224` (uncommitted working tree).
**Applies to:** the in-flight outcome-receipt redesign (prior audit item 8 implementation).

**Evidence / trigger path:**

1. Outer `$connection->transaction(closure, 3)` (attempts = 3 → Laravel retries on concurrency errors:
   deadlock / lock-wait timeout).
2. Attempt 1: preflight `lockForUpdate` finds no row → nested savepoint `$connection->transaction(fn () => $conversion->save(), 1)`
   commits the INSERT at savepoint level. The **model instance now has `exists = true`**.
3. A concurrency error (InnoDB deadlock) later in the same closure — reachable in `FraudDetectionService::analyzeConversion`
   (INSERTs fraud-signal rows) or `ApplyConversionAccounting::handle` (locks conversion → affiliate → balance) —
   rolls the whole outer transaction back and triggers attempt 2.
4. Attempt 2: preflight finds no row (rolled back) → `$conversion->save()` runs **`performUpdate`** because the reused
   model still has `exists = true` → `UPDATE ... WHERE id = <uuid>` matches **0 rows**, silently.
5. Attempt 2 proceeds to fraud analysis (writes fraud-signal rows for a row that does not exist), then
   `ApplyConversionAccounting::handle` → `AffiliateConversion::whereKey(...)->lockForUpdate()->firstOrFail()`
   (`ApplyConversionAccounting.php:36-39`) → **`ModelNotFoundException`**. Not a concurrency error → outer transaction
   rolls back and throws. (Rejected path instead dies earlier at `$conversion->refresh()`.)

**Impact:** the class of race the code comment explicitly claims to handle ("Concurrent same-key inserts deadlock on
gap locks under InnoDB; the retry replays the committed winner row") is only safe for deadlocks **during the insert**.
Deadlocks **after** the save convert a recoverable race into a confusing `ModelNotFoundException` surfaced to the
caller, despite retries remaining. Data integrity is preserved (attempt 2 fully rolls back; no orphan fraud signals
or duplicate conversions committed), and the docblock promise "Caller-level retry is safe" is broken for this window.
`OutcomeRedispatchTest` covers replay/rollback/validation/fraud/cross-connection but **not** post-save retry.

**Solution plan (small, package-local):**

1. Build the `AffiliateConversion` model **inside** the transaction closure so each attempt starts from a fresh
   instance (`exists = false`): move the `new AffiliateConversion([...])` + `forceFill` block from
   `RecordAffiliateOutcome.php:118-168` into the closure, above the preflight. All inputs used there
   (`$affiliate`, `$attribution`, `$metadata`, `$externalReference`, `$idempotencyKey`) are already computed
   outside and are pure; `CommissionCaps::clamp` and config reads are safe to re-run per attempt.
2. Add a regression test that simulates a concurrency error after save: a first-attempt-only listener (e.g. a
   `AffiliateFraudSignal::creating` hook or a `DB::listen` closure) that throws a `QueryException` with a deadlock
   SQLSTATE message on attempt 1 and passes on attempt 2; assert the final result is `Created` (or `Replayed`) and
   no `ModelNotFoundException`, with exactly one conversion row and matching fraud signals.
3. Optional hardening: keep a `$attempts` counter so the closure can also assert no double fraud-signal writes
   across attempts.

**Why not a wider fix:** attempts=3 semantics and the savepoint-unique-recovery design are correct; only model
instance reuse across attempts is broken.

---

### F-02 (Low, pre-existing) — MySQL functional unique index truncates at `CHAR(512)`, allowing false collisions on long tuples

**Status:** resolved (2026-10-10; complete tuple hashing, compiled MySQL/MariaDB DDL, and live MariaDB verification).
**Location:** `packages/contacting/database/migrations/2000_01_01_000001_create_contact_methods_table.php:143-160` (`createPrimaryUniqueIndex`, MySQL branch).

**Evidence:** the MySQL functional index stores `CAST(JSON_ARRAY(contactable_type, contactable_id, type, purpose)
AS CHAR(512))`. Worst-case JSON encoding (`["<255-char class>","<36-char uuid>","<255>","<255>"]`) exceeds 512
characters, so distinct tuples can truncate to the same 512-char key → a legitimate *is_primary* insert fails with
a unique violation and is indistinguishable from a real duplicate (the caller's recovery path fetches by tuple and
rethrows or misclassifies). Realistic tuples (~120 chars: app class names, `email`/`phone`, `general`) sit far below
512, so reachability is pathological today. The new MariaDB branch (F-02's sibling) is immune: it uses a SHA-256
length-prefixed hash in a 64-char column.

**Solution plan:** when convenient, switch the MySQL branch to the same SHA-256 length-prefixed hash expression the
MariaDB branch uses (either as a functional index or as a stored generated column), keeping the Postgres/SQLite
partial-index branches as-is. No data migration needed (index-only change; recreate the index). Low priority.

---

### F-03 (Refuted; originally claimed High) — Claimed raw visitor IP writes

**Status:** refuted and verified clean (R-01; hash-once persistence regressions pass). The original assertion and proposed fix below were incorrect.
**Locations:**
- `packages/affiliates/src/Support/Middleware/TrackAffiliateCookie.php:124` (`buildContext()`: `'ip_address' => $request->ip()`)
- `packages/affiliates/src/Support/Middleware/CaptureAffiliateReferralFromPath.php:78`
- `packages/affiliates/src/Actions/Affiliates/CapturePublicAffiliateReferral.php:74`

**Contract violated (both tables carry the same comment):**
- `2000_01_01_000002_create_affiliate_attributions_table.php:46`: `// Stores a SHA-256 pseudonymised hash (64 chars), never a raw address.`
- `2000_01_01_000006_create_affiliate_touchpoints_table.php:40`: same.

**Evidence:** `FraudDetectionService.php:130` correctly uses `IpHasher::hash($request->ip())`, so the package's own
normalizer exists and is the sanctioned path — the three sites above bypass it. `TrackAffiliateCookie` runs on every
tracked visit, so raw addresses accumulate on the hottest write path in the package. This is the same defect class
the 2026-10-06 share audit found in the ilmu360 adapter (`AffiliatesShareTrackingService.php:782/850/913`); the
package-side sites above are additional and upstream of it.

**Impact:** privacy/regulatory exposure (raw PII where a pseudonymised value is documented), plus a silent data-shape
lie for every downstream consumer that trusts the column comment. Retention/backfill obligations apply to existing rows.

**Solution plan (package-side fix benefits the app too):**
1. Replace `$request->ip()` with `IpHasher::hash($request->ip())` at the three sites (imports already available —
   the service is in the same package).
2. Grep for any remaining writers: `rg -n "ip_address.*=>.*ip\\(\\)" packages/affiliates/src` should return 0 after.
3. Add a package test asserting a tracked visit persists a 64-char hex value, not the request IP.
4. Retention decision (host/app level, mirrors share-audit item 3): decide whether existing raw rows are backfilled
   (hash) or wiped under a declared reporting-start rule — no silent rewrite.

---

### F-04 (Medium) — `cashier-chip` settles subscription periods without verifying the paid amount, and records the expected amount as ledger truth

**Status:** resolved (2026-10-10; actual amount/currency verification, terminal mismatch receipts, idempotent reconciliation, and regression tests).
**Locations:** `packages/cashier-chip/src/Actions/SyncChipPurchaseStatus.php:139-206` (`syncSubscriptionPayment`),
`:199-215` (`recordProcessedPurchase`).

**Evidence:**
1. `syncSubscriptionPayment` matches a paid purchase to a subscription by `client_id` + `purchase.metadata.subscription_type`
   only (`getSubscriptionType`, `HandlePurchasePaid.php:16-33`) and immediately advances `chip_status → Active` and
   `next_billing_at` (`:174-179`). The gateway's actual paid amount — available on the verified webhook payload at
   `$purchaseData->purchase` (`PurchaseDetailsData`, `packages/chip/src/Data/PurchaseData.php:17`) — is never compared
   against `$subscription->renewalAmount()`.
2. `recordProcessedPurchase` writes `'amount_minor' => $subscription->renewalAmount()` (`:200`) — the **expected**
   amount, not the actual charged amount — into `renewal_attempts.amount_minor`.

**Impact:** if the effective price changed between purchase creation and settlement (plan price edit mid-cycle, stale
renewal attempt, gateway-side adjustment), the subscription advances as fully paid for the period and the billing
ledger records a figure that matches neither the claim (`ClaimRenewalAttempt.php:75`) nor the actual charge.
Purchases are server-created and CHIP-signed, so this is a ledger-integrity gap, not an injectable charge —
bounded but real for a billing engine.

**Solution plan:**
1. In `syncSubscriptionPayment`, inside the locked transaction, read the actual amount
   (`$purchaseData->purchase->amount` / currency) and compare against `$subscription->renewalAmount()`.
2. On mismatch: do **not** advance `next_billing_at`; complete the attempt as `mismatch`, dispatch the existing
   `SettledPeriodPurchaseConflict` event (already wired for rollback recovery) with both amounts, and log. Let the
   host reconcile — same philosophy the action already uses for unique-violation conflicts (`:181-196`).
3. Record the **actual** paid amount in `RenewalAttempt::amount_minor` (column is already `integer` cast,
   `RenewalAttempt.php:128`); keep the expected amount in a new `expected_amount_minor` column only if
   reconciliation needs both (avoid schema churn otherwise).
4. Add tests: matching amount settles; mismatched amount holds settlement + dispatches conflict; duplicate purchase
   remains idempotent.

---

### F-05 (Low) — `ClaimScheduledPayout` loads every approved conversion to compute a FIFO prefix (unbounded memory)

**Status:** resolved (2026-10-10; bounded FIFO batches with skipped-row and early-stop regressions).
**Location:** `packages/affiliates/src/Actions/Payouts/ClaimScheduledPayout.php:212-244` (`fundedConversionAllocation`).

**Evidence:** the allocation loop needs only a FIFO prefix whose commission sum fits `$availableMinor`, but the query
fetches **all** approved, unpaid conversions for the affiliate (`->get()`, 3 columns, `orderBy(occurred_at)` then
`orderBy(id)`), materialising them before `break`ing. For a high-volume affiliate this is unbounded memory on a
scheduled/payout path. Correctness is fine (the write path locks rows via the `$lockForUpdate` parameter).

**Solution plan:** bound the scan — since every included row has `commission_minor >= 1`, the FIFO prefix can never
require more than `$availableMinor` rows, so add `->limit($availableMinor)` to the query before `->get()` (cheap,
order-preserving, and exact). Alternative: iterate `->cursor()`. No behavioural change; add a high-conversion-count
test fixture if cheap.

---

### Verified clean (checked and deliberately NOT findings)

| # | Claim checked | Verdict |
|---|---|---|
| V-01 | MariaDB migration's unique index on a **VIRTUAL** generated column (`primary_unique_hash`) | Supported — MariaDB docs confirm indexes (incl. unique) on VIRTUAL and PERSISTENT generated columns (mariadb.com, "Generated Columns" + indexes guide). Hash expression is collision-free (length-prefixed), `type`/`purpose` are NOT NULL so the CASE guard is sufficient, `SHA2(...,256)` fits `varchar(64)`. |
| V-02 | Events `RegistrationService::cancel()/reject()` "already-cancelled" branch calls `transitionStatus(Cancelled::class)` from `Cancelled` status → `InvalidTransition`? | **Refuted.** `EventRegistration::transitionStatus()` (`packages/events/src/Models/EventRegistration.php:207-237`) has a custom same-state guard that applies the lifecycle timestamp (`cancelled_at`) and returns without consulting the transition map. The defensive branch works as intended. |
| V-03 | Lock-order deadlock between `RecordAffiliateOutcome` accounting and `CreatePayout` | Consistent ordering in both: conversion → affiliate → balance (`ApplyConversionAccounting.php:31-46`, `CreatePayout.php:37-66` + `reserveBalance`). No AB-BA between these paths; payout overlap deadlocks are retried (`attempts: 3`). |
| V-04 | New business-key idempotency collides via `external_reference` (truncated to 120 chars) | `affiliate_conversions.external_reference` has **no** unique index (`2000_..._000003_create_affiliate_conversions_table.php`: only `idempotency_key` and `performance_bonus_key` unique). Truncation can only degrade reference lookups, not fail inserts. |
| V-05 | Removing `balancesSyncEnabled()` breaks installs without the balances table | Intentional behaviour change, documented in `packages/affiliates/docs/04-usage.md` (updated in the same working tree); package migrations ship `affiliate_balances` by default. Per repo policy, breaking changes are allowed. |
| V-06 | Docs drift for the changed `RecordAffiliateOutcome` signature | `04-usage.md:244-268` documents the new `AffiliateOutcomeResult` + disposition API correctly; in-repo callers (tests) updated. |
| V-07 | `RegistrationService::cancel()/reject()` transaction wrapping introduced listener-in-transaction hazards | Improvement: `transitionStatus` + domain event now atomic; rollback on listener failure. Standard Laravel queued-listener caveat applies (pre-existing pattern). |
| V-08 | `CommissionRuleEngine.php:605-606` `DB::raw("SUM({$revenueExpression})...")` SQL injection | Safe — `$conversionsTable`/`$affiliatesTable` come from `getTable()` (config-owned), `$revenueExpression` is derived from those; no user input interpolated. All repo `whereRaw` hits use bound `?` params. |
| V-09 | `DatabaseAffiliateLookup::findByColumn:94` interpolates `$column` into raw SQL | Safe — only two call sites, both pass literals (`'code'`, `'default_voucher_code'`); the value is bound. |
| V-10 | Checkout callback IP handling (`PaymentCallbackController.php:228`) | Safe — IP is hashed immediately into a rate-limit key, never persisted raw. |
| V-11 | Inventory stock decrement race (TOCTOU) | Safe — `InventoryService::ship()` locks the level row (`lockForUpdate`), checks availability, then atomic `decrement()` inside one transaction (`InventoryService.php:124-138`). |
| V-12 | Voucher redemption race / double-use | Safe — `RecordVoucherUsage` locks the voucher row, counts usages under the lock, and dedupes via `createOrFirst` on `(voucher_id, idempotency_key)` which is uniquely indexed (`voucher_usage` migration:36). |
| V-13 | Signals `/collect/server-outcome` trust | Safe — `VerifyTrustedSignalSignature` enforces HMAC-SHA256 over `timestamp.body`, replay window (30s floor) + cache-backed one-time-use guard, constant-time compare, fail-closed 503 when unconfigured. |
| V-14 | `DB::table()` owner scoping across packages | Safe at 6 checked sites — `CohortAnalyzer`, `PerformanceBonusService`, `MigrateGuestCartToUserAction`, `BulkGenerateVouchersAction` all apply `OwnerQuery::applyToQueryBuilder`/`CartOwnerScope`; `Promotion` model owner-aware throughout; `SyncManagementAssignmentToAuthzAction` pivot is authz (global-by-design, Spatie teams, not owner morphs). |
| V-15 | Cart/inventory/voucher event metadata carrying raw IP (`HasCartEventData:51`, siblings) | Observation, not a package defect — metadata is computed lazily and has **no package-internal persistence** (only the interface + host-facing contract, "for logging and tracing"). Flag for host apps: any host that serializes commerce events into logs inherits raw IPs. |
| V-16 | CHIP webhook signature bypass (OPEN-ISSUES #22) — re-verified today | **Still open**, unchanged at `ChipSpatieSignatureValidator.php:18-27`: `verify_signature=false` accepted in every non-production environment. Tracked; no new aspect found. |
| V-17 | Signals cross-tenant bypasses (commit `da0ed0c6a`) | Safe — `CrossTenantQuery` pairs every `withoutOwnerScope()` with re-applied property owner-tuple filters as defense in depth; explicit, greppable opt-out per the multitenancy rules. |
| V-18 | Repo-wide `::all()` usage | All hits are Spatie state-class enumeration (no DB model `::all()` on hot paths). |
| V-19 | `CohortAnalyzer:481/531`, `CommissionRuleEngine:639` unbounded `->get()` loops | Accepted for now — admin/scheduled analytics over explicit date windows; rule-engine leaderboard takes an explicit `$limit`. Revisit with `chunk`/`cursor` if windows widen. |
| V-20 | Working-tree test health | `OutcomeRedispatchTest` (9 tests, 38 assertions) and `CreateTrackingLinkActionTest` (5 tests, 15 assertions) pass with `--parallel`. F-01's post-save retry window is the one path no test exercises. |

---

## Progress log

| Sweep | Status |
|---|---|
| Uncommitted working tree (affiliates outcome redesign, contacting MariaDB migration, events registration transactions, CreatePayout, tests, docs) | Done — F-01, F-02, V-01..V-07 |
| Security sweep (webhooks, owner scoping, mass assignment, auth gaps, raw IP handling) | Done — F-03, V-08..V-11, V-13..V-16 |
| Money/consistency sweep (checkout, chip, cashier, inventory, vouchers/promotions) | Done — F-04, V-11, V-12, V-14; checkout reprice guard (B1) confirmed present at `CreateOrderStep.php:145-151` |
| Performance sweep (N+1, unbounded queries, missing indexes, chunking) | Done — F-05, V-18, V-19 |
| Recent-commit regression sweep (since 2026-09-25) | Done — spot-checked `da0ed0c6a` (signals concurrency: fix + 4 live race-test files, V-17), `d8d247413` (geography ladder, data-only), `e78d3fc3c` (test-only). No new findings. |

---

## Original audit summary (superseded by review and resolution)

**5 verified findings** (0 Critical, 1 High, 2 Medium, 2 Low) + 20 verified-clean claims across the uncommitted
working tree, security surfaces, money paths, performance patterns, and the last two weeks of commits.

- **F-03 (High)** is the top fix: three live package write paths store raw visitor IPs against an explicit
  "never a raw address" migration contract. Small diff, package-side, benefits the host app immediately.
- **F-04 (Medium)** is the top money-path fix: subscription settlement trusts the webhook without amount
  comparison and records the expected amount as ledger truth.
- **F-01 (Medium)** lives in the uncommitted outcome-receipt redesign — fix it in the same commit that lands
  that redesign (the concurrency window it opens is exactly what the redesign exists to close).
- **F-02, F-05 (Low)** are safe to batch into hygiene work.

Nothing found in this pass invalidated prior audit records; two tracked items (OPEN-ISSUES #22 CHIP signature
bypass) were re-verified as still open, and several candidate findings were refuted on re-verification (V-02,
V-03, V-04) — recorded so they are not re-raised.

---

## Independent review (Muse Code, 2026-10-07) — disagreements and corrections

The section below is **my review of this audit file**, not part of the original audit. I re-verified
every finding against source (plus vendor framework code where the claim depends on it) and ran the
cited tests. Verdict: **4 of 5 findings hold; F-03 is a false positive and its proposed fix is harmful.**
Corrected totals: **4 findings (0 Critical, 0 High, 2 Medium, 2 Low)**.

### R-01 — F-03 REFUTED (claimed High): no raw-IP writes exist in the affiliates package

The three cited sites build **context arrays**, not database rows, and every package persistence path
hashes before writing:

- `TrackAffiliateCookie.php:124`, `CaptureAffiliateReferralFromPath.php:78`,
  `CapturePublicAffiliateReferral.php:74` all feed `TrackAffiliateVisit::handle()` /
  `TouchAffiliateAttribution::handle()`, which persist `IpHasher::hash(...)` at
  `TrackAffiliateVisit.php:133`, `TouchAffiliateAttribution.php:63-65`,
  `AttachAffiliateToCart.php:161`.
- Full-repo enumeration: the only `new AffiliateTouchpoint` in all of `packages/`
  (`AttachAffiliateToCart.php:175`) omits `ip_address` entirely; the only
  `new AffiliateAttribution` sites are the two hashing actions; no `::create` /
  `updateOrCreate` / `firstOrCreate` on attributions, no `->ip_address =` assignment, and no
  Filament form input for `ip_address` exist anywhere. No rule persists `$request->ip()` raw.
- Hashing predates the audit (introduced 2026-09-15 in `67853fb4d`); the migration contract
  "never a raw address" is currently **satisfied**, and `mergeMetadata` strips `ip_address` so there
  is no JSON-column leak either.

**Do not apply the F-03 solution plan:** hashing at the three context builders double-hashes
(`hash(hash(raw))` ≠ `hash(raw)`, verified by execution), forking new rows away from historical rows
and fraud contexts and silently breaking IP-equality checks. F-03 should be moved to verified-clean.
Genuine residual (defense-in-depth, not a live defect): the models have no hashing mutator, so a host
app writing to the tables while bypassing the actions could store raw values.

### R-02 — F-01 CONFIRMED (Medium), mechanism refined

Retry chain verified in vendor source: `transaction($cb, 3)` retries only message-matched concurrency
errors, `DeadlockException` from the savepoint level preserves the message so the outer retry works,
`ModelNotFoundException` matches nothing and rethrows after rollback, and `refresh()` →
`firstOrFail()` confirms the rejected-path death. Refinement: attempt-2 `save()` is not a "0-row
UPDATE" — the model is clean after attempt 1 (`exists = true`, not dirty), so `save()` returns `true`
without issuing any query (`Model.php:1419-1422`). More silent than described; same outcome. Fraud
writes are conditional per rule (5s default window, 50/day default cap, self-referral config+match),
but the `ModelNotFoundException` outcome is unconditional regardless of fraud.

### R-03 — F-04 CONFIRMED in substance (Medium), citations corrected

- `getSubscriptionType` is in `SyncChipPurchaseStatus.php:227`, not `Actions/HandlePurchasePaid.php`
  — that path never existed in git history; the listener is and was
  `Listeners/HandlePurchasePaid.php`.
- `$purchaseData->purchase->amount` names a nonexistent field. The real accessor chain is
  `PurchaseEvent::getAmount()` → `PurchaseData::getAmountInCents()` →
  `purchase->getTotalInCents()` (int cents); `PurchaseDetailsData` carries `total`/`debt` as
  `Money` objects.

### R-04 — F-02 CONFIRMED in mechanics (Low), one sentence unverified

Worst-case math holds under the actual 191-char limits (~625 encoded chars > 512). But "the caller's
recovery path fetches by tuple and rethrows or misclassifies" describes code that does not exist —
zero unique-violation handling in all of `packages/contacting/src`. Real behavior on a false
collision is a raw `QueryException` propagating to the caller.

### R-05 — F-05 CONFIRMED (Low), verified-clean spot checks hold

F-05 confirmed exactly as written. Spot re-checks of V-01, V-02, V-03, V-04, V-10, V-16, V-17 all
hold; V-20 re-ran green in this session (9 passed / 38 assertions, 5 passed / 15 assertions).
V-05–V-09, V-13, V-15, V-18, V-19 were not individually re-checked. Header nits: repo state matches
(`e78d3fc3c` + working tree, 69 packages), but `OPEN-ISSUES.md` lives at the repo root, not under
`audits/` (its "66 items" count is exact and the CHIP bypass is genuinely item 22 — only the path
prefix was wrong), and several cited line numbers have drifted by ~5 lines.

---

## Resolution (Muse Code implementation, 2026-10-10)

Single implementation owner: Muse Code. Codex independently accepts the finished
source/evidence. No commits, stashing, resets, dependency changes, payments, external
messages, or deployment were performed. Unrelated dirty changes preserved.

- **F-01 (Medium) — RESOLVED.** `RecordAffiliateOutcome` now constructs a fresh
  `AffiliateConversion` inside each transaction attempt and resolves the outer
  transaction from a fresh model's connection. Preflight, savepoint unique recovery,
  atomic accounting/fraud, and after-commit redispatch retained. Regression at the
  bottom of `tests/src/Affiliates/Feature/OutcomeRedispatchTest.php` repaired (no
  mock of the final `FraudDetectionService`; first-attempt-only deadlock via a scoped
  `AffiliateFraudSignal::creating` hook, `DB::commit()` fixture handling with cleanup).
  Red: both datasets fail with `ModelNotFoundException` on pre-fix code. Green: full
  file passes (11 tests). `FraudDetectionService` left `final`.
- **F-02 (Low) — RESOLVED.** Both contacting migrations now use a nullable VIRTUAL
  generated SHA-256 length-prefixed hash column plus an ordinary unique index for the
  whole MySQL family (MySQL and MariaDB); the `CAST(... AS CHAR(512))` functional key
  is gone. Null/non-primary semantics preserved; Postgres/SQLite partial indexes
  unchanged. `SocialProfile` hides the new visible MySQL helper column (MariaDB keeps
  it invisible); `ContactMethod` already hid its hash. Regression:
  `tests/src/Contacting/Database/PrimaryUniqueHashTest.php` (long 512-prefix tuples,
  exact duplicates, delimiter tuples, non-primary/null parents, plus compiled-DDL
  tests that execute both owning migrations under Laravel's real MySQL/MariaDB
  schema/query grammars in pretend mode with stub PDO server versions and assert the
  virtual `SHA2(CONCAT(CHAR_LENGTH...))` covers every scoped column with the
  non-primary/null guard, the ordinary unique targets the helper hash, no
  `CHAR(512)`/`JSON_ARRAY` key remains, and MariaDB social DDL is `invisible`).
  Codex also executed both actual migrations on an isolated MariaDB 11.4.13 instance
  and verified long tuples, complete hash values, exact duplicate rejection,
  delimiter-containing tuples, non-primary duplicates, and null-parent duplicates.
  The temporary instance used only task-owned data and a Unix socket with TCP disabled;
  it was shut down and removed afterward. MySQL DDL compilation passes, but live
  MySQL execution remains unverified.
- **F-03 (High, refuted) — VERIFIED CLEAN, no code change.** Independent review R-01
  stands: context builders stay raw by design; `TrackAffiliateVisit`,
  `TouchAffiliateAttribution`, and `AttachAffiliateToCart` hash exactly once at
  persistence. Added HTTP/persistence regressions to
  `tests/src/Affiliates/Unit/PublicAffiliateReferralTest.php` proving stored
  `ip_address` equals one `IpHasher` hash of the request IP (64 hex, not raw, not
  double-hashed), metadata excludes the raw IP, and touch preserves/re-hashes once.
  No backfill, no model mutator.
- **F-04 (Medium) — RESOLVED.** `SyncChipPurchaseStatus` now compares the gateway's
  actual `PurchaseData->getAmountInCents()` / `getCurrency()` against the locked
  `renewalAmount()` and the billable's `preferredCurrency()`. Mismatches leave
  status/billing dates unchanged and terminally record `mismatch` with the actual
  amount, cleared lease, `completed_at`, and `AMOUNT_MISMATCH`/`CURRENCY_MISMATCH`;
  `alreadyProcessed` covers `mismatch`; matching settlements record the actual charge
  (stale claims overwritten via query update, bypassing the frozen-amount guard by
  design). `SettledPeriodPurchaseConflict` carries expected/actual amounts and
  currencies for mismatches; reconciliation event/log publish after commit. When the
  billing period is free, the mismatch keeps the period key and blocks fresh claims;
  when an unrelated pending claim (null or distinct purchase) holds the period, the
  mismatch receipt uses a null period key (unique allows multiple nulls) so the
  pending claim is preserved for the correct purchase, while the event/log still
  carry the known billing-period key with full reconciliation; redelivery is
  idempotent and a later correct purchase still settles the preserved claim. Only
  terminal `completed`/`mismatch` suppress a new mismatch receipt; a pre-existing
  `failed`/`skipped` row never swallows a paid mismatch. Existing matching-amount
  distinct-purchase unique-conflict rollback preserved. Renewal workers
  (`ClaimRenewalAttempt`, `RenewSubscriptionsCommand`) only touch `claimed`/`unknown`,
  so `mismatch` is never resumed; no `Subscription::renew()` exists (renewal path is
  the command + claim action). Tests: new `RenewalAmountMismatchTest` (16 tests:
  pending-null, pending-distinct-purchase, colliding duplicates, colliding outer
  rollback, later matching settlement, failed pre-existing, plus the original
  matching/mismatch/idempotency/owner cases) plus corrected nested-`purchase.total`
  fixtures in existing settlement tests. Docs `09-subscriptions.md` /
  `10-webhooks.md` updated.
- **F-05 (Low) — RESOLVED.** `fundedConversionAllocation` iterates `lazy(1000)`
  instead of `get()`, preserving `occurred_at,id` FIFO order and `lockForUpdate`;
  no `limit(availableMinor)` (skipped rows must not consume a limit) and no
  `lazyById` (would reorder FIFO). Regression:
  `tests/src/Affiliates/Feature/ScheduledPayoutAllocationTest.php` (skipped-row
  prefix, first-unfundable stop with later cheap exclusion, currency case
  normalization, ≤1,000 hydrated rows on early break over 5,002 rows, 3 fixed-size
  chunk selects over 2,100 rows).

Out-of-scope distinction: **V-16 (CHIP webhook signature bypass, OPEN-ISSUES #22) is
separately tracked and is not a new finding in this audit**; it was deliberately not
folded into F-04 or re-reported here.

### Independent acceptance (Codex, 2026-10-10)

Accepted the final source and behavior-to-test mapping after a bounded Muse correction
for pending-period mismatch receipts and compiled driver DDL. Existing unrelated diffs
were compared with the pre-dispatch snapshot and are unchanged.

Final focused checks passed: outcome retries (11), hash-once referrals (8), mismatch
settlement/reconciliation (16), compiled-DDL and contact uniqueness (6), existing
contact indexes (2), and bounded payout allocation (5). Related settlement, webhook,
renewal-worker, multicurrency, and event tests also passed. The earlier contacting
package run passed 365 tests; the subsequently strengthened database tests passed
separately. Pint and package-scoped PHPStan level 6 passed for the affected packages.
Live MariaDB verification exited 0. No full-repository clean bill is implied: prior
OPEN-ISSUES records remain separate, and live MySQL verification is still a release check.
