# Cross-package audit — rewards / referrals / filament adapters (2026-10-14)

Read-only audit. Scope: `packages/rewards`, `packages/filament-rewards`,
`packages/referrals`, `packages/filament-referrals`, maintained tests under
`tests/src/{Rewards,FilamentRewards,Referrals,FilamentReferrals}`, and
`commerce-support` as read-only contract reference. Read first: `CONTEXT-MAP.md`,
all four package `CONTEXT.md` files, package docs 01/03/04/99, `.ai/rules/index.md`
(no area rule covers these packages). Two findings were reproduced with a
throwaway probe at `/tmp/ReferralLifecycleProbeTest.php` (not committed).

## Findings

### CRITICAL XP-01 — Two-sided `reward_points` fulfilment mints every beneficiary twice

- Where: `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php:26,59`
  (side-scoped key + whole-job `dispatchSync`) composed with
  `packages/rewards/src/Support/RewardEvaluationCandidates.php:44-54,120-128`
  (every fact yields sharer **and** actor candidates for every component).
- Failure: referrals docs instruct the host reader to return a fact with
  referrer=sharer and referee=actor for each side key
  (`packages/referrals/docs/04-usage.md:113`). Fulfilling the referrer side
  therefore evaluates both people under `...:referrer`; fulfilling the referee
  side evaluates both again under `...:referee`. Canonical uniqueness, claims
  and the preset `recipient_source_component` cap all key on the business key,
  and the side keys differ, so nothing dedupes across sides.
- Probe (both beneficiaries enrolled, one 10-point program, specs 10/10):
  `RewardAward` count was **2 after the first fulfilment and 4 after the
  second**, against 2 frozen promises. Each person holds two awards.
  Different per-side amounts (validator allows them) cannot be honoured either:
  one program component has a single `points` value
  (`RewardRuleValidator.php:297-300`), so one side fails the amount check
  *after* its award was already minted at the wrong amount (existing test
  encodes the 20-point side failing without asserting total awards).
- Minimal fix: stop dispatching the whole-fact job from the adapter. Evaluate
  only the frozen side's (role, recipient) — either add a role/recipient-scoped
  evaluation entry in rewards, or have the adapter call
  `EvaluateRewardEntitlementAction` for the single matching candidate. Until
  per-role amounts exist, also reject `reward_points` specs whose two sides
  disagree, or document that both sides must promise identical points.
- Regression test: extend the points test in
  `tests/src/Referrals/ReferralLifecycleTest.php:328` to assert total
  `RewardAward::count()` is 1 after the first fulfilment and 2 after the
  second, with recipient/role per award.

### HIGH XP-02 — Referral spec never binds a rewards program/family; any program's award fulfils

- Where: `packages/referrals/src/Support/ReferralRewardSpecValidator.php:21`
  (spec keys are only driver/amount/points/currency/voucher) and
  `RewardsPointsFulfillment.php:65-71` (probe is `first()` over
  business key + recipient + component, unordered, any program).
- Failure: two active rewards programs matching `referral_qualified` (e.g. a
  tenant program plus a global one under `include_global`) race in
  family-key order; stacking makes the loser a rejection and the winner —
  possibly the unintended program — fulfils the referral. On amount mismatch
  the referral goes `Failed` while the minted award remains live in rewards.
- Minimal fix: freeze an optional `family_key` (or program id) in the
  `reward_points` spec, filter candidates and the probe by it, and order/limit
  the probe deterministically.
- Regression test: two active programs awarding different points; only the
  bound family's award exists and fulfils; the other program creates nothing.

### HIGH XP-03 — Queued `EvaluateRewardEntitlementJob` cannot run without ambient owner context

- Where: `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php:43-45`
  (payload is only `businessKey`; no `OwnerContextJob`/owner fields) →
  `RewardEvaluationCandidates.php:63` (`RewardProgram::query()` applies
  `OwnerScope`, commerce-support `OwnerScope.php:20-24` asserts resolved owner).
- Failure: on a real queue worker (no web auth, host resolver returns null —
  the documented production shape), the job throws
  `NoCurrentOwnerException` before evaluating anything, retries 3×, dies.
  Probe verified: null resolver + cleared override → job `handle()` throws
  that exception. Tests mask this: `tests/src/TestCase.php:141-149` binds a
  `FixedOwnerResolver`, and referrals only ever calls the job synchronously
  inside an owner context.
- Minimal fix: fan out by program owner inside the job (discover owner tuples
  like `OwnerBatchRunner` over `RewardProgram`, evaluate per owner with
  `OwnerContext::withOwner`), or carry an explicit owner payload via
  `OwnerContextJob`/`OwnerScopedJob` and document that dispatchers must set it.
- Regression test: dispatch the job through the queue (serialized) with a
  null-returning resolver and a tenant-owned active program; assert the award
  is created for that owner only.

### HIGH XP-04 — Corrections sweep, recheck job and corrections command lack per-owner wrapping

- Where: `packages/rewards/src/Actions/ApplyRewardSourceCorrectionsAction.php:118,132`
  (guarded `TransitionRewardAwardAction`, catch only `InvalidArgumentException`),
  `packages/rewards/src/Jobs/RecheckRewardAwardsJob.php:117,126` (same catch),
  `packages/rewards/src/Commands/ApplyRewardSourceCorrectionsCommand.php:73`
  (no owner iteration — contrast
  `packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php:38-74`,
  which iterates owner tuples correctly).
- Failure: `TransitionRewardAwardAction` → `RewardProgramGuard::findAwardOrFail`
  → `OwnerWriteGuard` asserts an owner. `NoCurrentOwnerException` extends
  `CommerceException extends RuntimeException` (not `InvalidArgumentException`),
  so it escapes both per-award catches: an operator running the documented
  `rewards:apply-source-corrections` CLI, or a queued recheck, aborts on the
  first award of any owned program instead of settling corrections.
  `tests/src/Rewards/Ledgers/RewardCorrectionSweepTest.php:132` passes only
  under the fixed test resolver with global fixture programs.
- Minimal fix: group awards by program owner and wrap each unit in
  `OwnerContext::withOwner` (or reuse `OwnerBatchRunner` over
  `RewardProgram`); treat missing-owner as a per-award error entry as a floor.
- Regression test: artisan sweep + queued recheck with null resolver against
  a tenant-owned program's award; assert reversed/rejected, no exception.

### HIGH XP-05 — `reward_points` reversal reports success without reversing any points

- Where: `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php:94-104`:
  `reverse()` only stamps `rewards_reversal=pending_host_corrections_sweep`
  and returns `fulfilled(...)`; `ReverseReferralReward.php:66-68` then marks
  the referral `Reversed` and demotes the invite. Neither package bridges the
  stamped key into rewards: the sweep reads only explicit `--keys` or the
  bound `RewardCorrectionKeySource` (default null,
  `ApplyRewardSourceCorrectionsAction.php:190`), and `RecheckRewardAwardsJob`
  additionally never touches fulfilled awards (its docblock, :33-37).
- Failure: after a fraud reversal in referrals/Filament, the rewards award
  stays approved/fulfilled and its points remain spendable indefinitely unless
  the host happens to hand-carry the metadata key into a corrections sweep.
  Referrals docs disclose the pending state (`docs/04-usage.md:121`) but no
  code completes it. The other rails really reverse (voucher expire,
  affiliate conversion reversal).
- Minimal fix: in `reverse()`, drive the rewards correction for the business
  key (recheck/sweep entry) and verify the award is rejected/reversed or a
  recovery opened; otherwise return `failed('pending…')` so the referral does
  not claim a completed reversal.
- Regression test: reverse a fulfilled points reward and assert the rewards
  award is reversed/rejected (or recovery opened for fulfilled) and the point
  balance no longer includes it — the current test at
  `ReferralLifecycleTest.php:424-428` asserts only the metadata stamp.

### MEDIUM XP-06 — `RewardProgramGuard` hardcodes `includeGlobal: true`, contradicting config and docs

- Where: `packages/rewards/src/Support/RewardProgramGuard.php:50` vs
  `config/rewards.php:50` (`include_global` default false, honoured by
  `OwnerScope` queries), rewards `docs/03-configuration.md:33` and
  filament-rewards `docs/99-troubleshooting.md:9`. Referrals' guard reads its
  config instead (`ReferralProgramGuard.php:25`).
- Failure: with `include_global=false`, global programs vanish from lists and
  from job candidates, yet any tenant that knows a global program's UUID can
  still write against it through every guarded action (enroll, evaluate,
  preview, transitions) — the reverse of the documented "global writes need
  explicit global context". Cross-package: a global rewards program silently
  cannot fulfil tenant referrals through the job unless `include_global` is
  flipped, which no referral doc mentions.
- Minimal fix: pass `(bool) config('rewards.owner.include_global', false)` in
  the guard (mirroring referrals) and document the referrals global-program
  requirement; keep explicit-global-context callers able to write.
- Regression test: tenant owner + `include_global=false` cannot evaluate or
  preview a global program; with explicit global context it succeeds.

### LOW XP-07 — Documented expiry command options do not exist

- `packages/rewards/docs/04-usage.md:99` shows
  `rewards:expire-point-lots --actor=<uuid> --actor-role=staff`, but the
  signature is only `--as-of`
  (`packages/rewards/src/Commands/ExpireRewardPointLotsCommand.php:15`);
  copy-paste fails with "option does not exist". Tests call it correctly
  (`RewardFinanceLedgerTest.php:345`), so only the docs are wrong. Fix by
  correcting the example (the command intentionally acts as `system`).

### LOW XP-08 — Composer `suggest` entries promise adapters that do not exist

- `packages/rewards/composer.json:18-19` claims affiliates/vouchers enable a
  payout-executor/coupon adapter, and
  `packages/filament-rewards/composer.json:17-18` claims affiliates /
  filament-vouchers enable a fraud-signal resource and voucher deep links.
  No such classes or conditional registrations exist (only manual/null
  defaults ship; the Filament plugin registers all resources
  unconditionally and its comment says rails stay host-bound). Reword the
  suggestions as host-implemented integrations or ship the adapters.

### LOW XP-09 — Referrals maintained test fallback schema drifts from real migrations

- `tests/src/Referrals/ReferralsTestCase.php:118` uses
  `nullableMorphs('owner')` (bigint id) vs migrations'
  `nullableUuidMorphs`, and `:167` `unsignedInteger('points')` vs
  `unsignedBigInteger` (`2026_10_14_000003…:23`). The base `TestCase` loads
  rewards migrations (`tests/src/TestCase.php:514`) but not referrals', so the
  hand schema is a live fallback whenever provider migrations have not run;
  on Postgres the drift can hide real migration failures (uuid owner ids).
  Prefer loading package migrations and deleting the fallback; assert column
  types in `tests/src/Referrals/InstallationTest.php` like rewards does for
  its owner morph.

## Explicit false positives (verified, do not file)

1. **Cap-counter monotonicity.** `RewardCapCounter.consumed` only ever
   increments (`EvaluateRewardEntitlementAction.php:728`; appeal overturn
   re-consumes at `TransitionRewardAwardAction.php:1190`; the :923 site only
   asserts the held obligation). Rejection/reversal never decrements. That is
   conservative: a reverse-then-requalify cycle cannot re-spend quota or mint
   past a cap. Not a bypass.
2. **Global-program inclusion oracle.** `RewardProgramGuard.php:46`
   distinguishes unknown id (`InvalidArgumentException`) from out-of-scope id
   (`AuthorizationException` via `OwnerWriteGuard`). Exploiting it requires
   already knowing the victim program's unguessable UUID; no enumeration or
   data disclosure follows. The real defect in this area is the write-side
   include-global inconsistency (XP-06), not the oracle.
3. **Recipient-keyed point/payee primitives.** Point ledger actions, payee
   actions and `rewards:expire-point-lots` carry no program guard by design:
   lots/operations are keyed `(recipient_type, recipient_id, family_key,
   unit)` and `family_key` is **globally unique**
   (`2001_05_01_000001…:18`), so scopes cannot collide across owners; payees
   are program-less until a payout request (program-scoped) links them, as
   filament-rewards `docs/99-troubleshooting.md:13` states. Callers still pass
   the shared `assertActor` contract. No cross-tenant merge was found.
4. **Null readiness defaults.** Provider binds null reader/readiness/
   financial/reservation/correction/coupon implementations
   (`RewardsServiceProvider.php:54-64`). Each fail-closes on inspection:
   reader returns `null` (evaluation refuses `unknown_source`), financial
   checker always returns `ready:false` with blockers, readiness checker
   blocks money/benefit activation, reservation resolver returns `false`,
   coupon fulfillment throws, correction source yields `[]`. The real
   `Funded*` checkers are documented opt-in host bindings
   (`docs/99-troubleshooting.md:17`) and prove funding/payee from rows. The
   only non-null defaults are the manual payout rail (never auto-confirms;
   awaits independent operator confirmation) and the morph recipient
   resolver/catalog service. Documented fail-closed setup — not a finding.

## Contract surfaces checked clean

- **Optional-dependency safety:** referrals registers voucher/affiliate/
  reward-points adapters only when the driver is enabled *and* all symbols
  exist (`ReferralsServiceProvider.php:43-76`); the router re-checks config +
  class at call time (`ReferralRewardFulfillmentRouter.php:41-46`) and each
  adapter re-checks `class_exists`. Optional packages are `suggest`, not
  `require`; Filament packages hard-require their core package, so
  unconditional resource registration is safe.
- **Config discipline:** every key in `rewards.php`, `referrals.php`,
  `filament-rewards.php`, `filament-referrals.php` was traced to a read
  (tables via model `getTable()`, owner triples via
  `OwnerScopeConfig::fromConfig`, registries via `RewardRuleValidator`/
  `RewardLedgerEntries`, navigation via `getNavigationGroup/Sort`,
  `staff_roles`/`owners.types` via the presenter/form). No decorative keys;
  section order follows Database → Ownership → Defaults → Features and
  Navigation-first for Filament; no static `$navigationGroup` or
  `navigation_group` key remains.
- **Migrations:** no `constrained()`/cascades/FK constraints, no `down()`,
  no soft deletes in either core package; UUID primaries; JSON columns go
  through `commerce_json_column_type()` honouring each package's
  `json_column_type` config; money is `amount_minor`/minor-units + currency.
- **Amount contract (partial):** the adapter's points check is effective for
  validated versions because `amount_snapshot` is the frozen component entry
  and the validator requires integer `points`
  (`EvaluateRewardEntitlementAction.php:919-920,982`,
  `RewardRuleValidator.php:298-300`). The minting defect is XP-01/XP-02, not
  the comparison itself.
- **Referral expire command** is the owner-batch reference implementation
  (tuple iteration + per-owner guard re-resolution); rewards commands/jobs
  should converge on it (XP-03/XP-04).

## Test-coverage gaps for the claims above

- Points rail test never asserts total award count or the other beneficiary
  (XP-01 shipped green); the reversal leg asserts metadata only (XP-05).
- No serialized/queued job test exists in `tests/src/Rewards` (jobs are
  invoked via `handle()` directly); nothing runs jobs/commands with a
  null-returning owner resolver (XP-03/XP-04).
- No cross-owner reward-points test (referral owner ≠ rewards program owner,
  global program) exists in `tests/src/Referrals` (XP-02/XP-06).
