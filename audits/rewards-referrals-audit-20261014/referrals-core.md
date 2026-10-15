# Referrals Core Audit — `packages/referrals`

Read-only audit, 2026-10-14. Scope: `packages/referrals` (all `src`, `config`,
`database/migrations`, `docs/01,03,04,99`, `CONTEXT.md`), tests in
`tests/src/Referrals` (21 passed, `./vendor/bin/pest --parallel tests/src/Referrals`),
plus read-only contract spot-reads in `packages/vouchers` (`CreateVoucher`, voucher
usage-limit scope) and `packages/rewards` (`EvaluateRewardEntitlementJob`,
`RewardCorrectionKeySource`/`NullRewardCorrectionKeySource`) needed to verdict the
adapter claims. No repository files were edited.

Severity rubric (task): critical = cross-tenant mutation/disclosure, money/points
mint/spend, fail-open reversal, authorization bypass. High = deterministic broken
core flow, unsafe missing-owner job/command behavior, unlimited redeemable value,
write action bypassing the package guard.

## Verdict on assigned claims

| Claim | Verdict |
|---|---|
| `RewardsPointsFulfillment.reverse` claims success while live points awards remain | **Confirmed — REF-01 (critical)** |
| Points mismatch after `dispatchSync` can orphan a live award | **Confirmed — REF-02 (high)**, plus REF-03 bypass |
| `VouchersReferralFulfillment` can issue unlimited/multi-use or non-beneficiary-bound vouchers | **Confirmed — REF-04 (high)**, plus REF-05 deterministic code |
| `ExpireReferralInvitesCommand` locks/queries all owners then guard can roll back batch | **Confirmed — REF-06 (high)** |
| `expired_at` used as future deadline instead of `expires_at` + transition timestamp | **Confirmed — REF-07 (medium)** |
| Lifecycle, caps, single-claim, owner guards, affiliate policy, optional-driver fail-closed | Mostly sound (see Verified OK); two medium gaps REF-08, REF-09 |

## Findings

### REF-01 CRITICAL — Rewards-points reversal is fail-open
- Body: `src/Fulfillment/RewardsPointsFulfillment.php:94-104` — `reverse()` writes
  `rewards_reversal = pending_host_corrections_sweep` + business key into metadata,
  saves, and returns `ReferralFulfillmentResult::fulfilled(...)`. It never touches the
  Rewards award, never enqueues a correction, and never verifies one happened.
- Caller: `src/Actions/ReverseReferralReward.php:51-68` trusts `success`, sets reward
  `Reversed` + `reversed_at`, demotes a `Rewarded` invite to `Qualified`, and dispatches
  `ReferralRewardReversed`. Downstream observers see a completed reversal.
- Failure scenario: default install. Rewards binds `NullRewardCorrectionKeySource`
  (verified: `taintedKeys(): []`, `causeFor(): null`), so the corrections sweep no-ops
  unless the host writes a custom key source that knows to read referrals metadata —
  nothing in referrals registers or provides one. Operator reverses a fraudulent
  referral; referrals says `Reversed`; the beneficiary keeps spendable points forever.
  Docs (`04-usage.md`, Rewards points) disclose the pending-sweep design, but disclosure
  does not make it fail-closed: the API contract (`ReferralRewardFulfillment::reverse`
  returning success) and the terminal `Reversed` state both assert completion, and the
  reversed reward can never be re-fulfilled or re-reversed to repair it.
- Test gap: `ReferralLifecycleTest.php:424-428` asserts `Reversed` + pending metadata
  and never asserts the Rewards award changed — the test enshrines the fail-open path.
- Minimal fix: `reverse()` returns `failed()` unless it can synchronously verify the
  award is corrected/non-live (or a distinct non-success pending result is introduced
  and `ReverseReferralReward` refuses to mark `Reversed` on it).
- Regression test: fulfil a points reward, reverse it, assert either reversal fails
  (reward stays `Fulfilled`) or the `RewardAward` for the business key is no longer in
  a live status and points are not spendable.

### REF-02 HIGH — Points mismatch after `dispatchSync` orphans a live award
- Body: `src/Actions/FulfillReferralReward.php:30` wraps fulfilment in
  `DB::transaction`; `RewardsPointsFulfillment.php:59` runs
  `EvaluateRewardEntitlementJob::dispatchSync()` inside it. The job evaluates every
  active Rewards program candidate for the fact (job `handle()` loop) and creates
  award(s) on the same connection. The amount check at `:84-89` runs *after* creation:
  on mismatch it returns `failed()`, and `FulfillReferralReward.php:60-63` marks the
  referral reward `Failed` and returns normally — the outer transaction **commits**,
  persisting the wrong-amount Rewards award.
- Failure scenario (already encoded in tests): frozen spec 20 points, Rewards rule
  awards 10 → referral reward `Failed` with "does not match…" (`ReferralLifecycleTest.php:419-422`),
  while the 10-point award is live in Rewards. Retrying re-dispatches the same business
  key, finds the same award, mismatches again — permanent orphan: points spendable,
  referral side forever `Failed`, invite never `Rewarded`. The post-hoc check also
  verifies only `->first()` (unordered, `:65-71`) although the job may create awards in
  several programs; extra awards are never examined.
- Why high not critical: minting requires host rule/spec misalignment, which docs
  explicitly warn to keep aligned; the defect is that the guard detects misalignment
  but leaves the minted value in place with no compensation.
- Minimal fix: on mismatch, do not commit-and-fail — roll back the evaluation (throw so
  the outer transaction aborts before `markFailed` persists in a separate transaction),
  or reverse/cancel the created award before returning `failed()`.
- Regression test: 20-point spec vs 10-point rule → fulfil → assert referral `Failed`
  **and** zero live `RewardAward` rows for the business key.

### REF-03 MEDIUM — Points-amount verification is skippable
- Body: `RewardsPointsFulfillment.php:80-89` reads points from
  `$award->amount_snapshot['points']` and `$reward->spec['points']`, failing only when
  the key exists **and both are `is_int`** and differ. Missing/non-int snapshot points
  (shape drift in Rewards) or a spec without `points` skips verification and returns
  `fulfilled()` at `:91`. The frozen integer also exists as `$reward->points` column
  but is never compared.
- Minimal fix: require `is_int($awardedPoints) && $awardedPoints === $reward->points`,
  else `failed()`.
- Regression test: award whose snapshot lacks `points` (or holds a string) must fail
  fulfilment, not fulfil.

### REF-04 HIGH — Referral vouchers can be unlimited, mis-valued, bearer instruments
- Validator: `src/Support/ReferralRewardSpecValidator.php:73-88` requires only a
  non-empty `voucher.type` and an integer `value >= 0` (`voucher['value'] ?? $amountMinor`
  — an explicit voucher value silently overrides/diverges from frozen `amount_minor`;
  `0` is accepted). Every other `CreateVoucher` field passes through unchecked.
- Adapter: `src/Fulfillment/VouchersReferralFulfillment.php:40-46` forwards
  `$spec['voucher']` verbatim, overriding only `code`, `currency`, `metadata`. It never
  forces `usage_limit`/`usage_limit_per_user`, never sets an expiry, and never binds the
  frozen beneficiary (no wallet entry / holder for `beneficiary_type/id`).
- Contract check (vouchers): `CreateVoucher.php:66-67` defaults both usage limits to
  `null`; the voucher availability scope treats `usage_limit IS NULL` as unlimited
  (`Voucher.php:280-281`).
- Failure scenario: spec `['driver'=>'voucher','amount_minor'=>100,'voucher'=>['type'=>'fixed','value'=>999999]]`
  freezes a 100-minor promise but issues a 999999-value bearer code redeemable by anyone
  who learns it, unlimited times. Even a "correct" spec with no limits yields a multi-use
  bearer voucher for a one-beneficiary reward. Matches rubric "unlimited redeemable value".
- Minimal fix: validator rejects/normalizes the voucher array to an allowlist; adapter
  forces `usage_limit = 1`, `usage_limit_per_user = 1`, `value === amount_minor`, and
  binds the beneficiary (wallet/holder) before returning success.
- Regression test: fulfil with unlimited/mismatched-value spec → assert rejection, or
  issued voucher is single-use, correctly valued, and bound to the beneficiary; second
  redemption by another holder fails.

### REF-05 MEDIUM — Voucher code is deterministic from the invite UUID
- Body: `VouchersReferralFulfillment.php:24` code = `REF` + first 24 hex of
  `sha256($reward->idempotency_key)`; `QualifyReferral.php:181` idempotency key =
  `sha256('referrals:reward:{inviteId}:{side}')`. Anyone who learns an invite UUID
  (URLs, events, logs, admin) precomputes both sides' bearer codes before issuance.
  Combined with REF-04 (bearer, possibly unlimited) this is precomputable money.
- Minimal fix: random voucher code; keep idempotency solely in voucher metadata and
  resolve replays by metadata lookup.
- Regression test: issued code is not derivable from invite id/side/idempotency key;
  replay still returns the same code.

### REF-06 HIGH — Expire command locks all owners' invites, then rolls back and crashes
- Body: `ExpireReferralInvitesCommand.php:38-74` correctly iterates distinct owner
  tuples and enters `OwnerContext::withOwner(...)` per owner. But
  `expireCurrentOwner()` at `:85-91` queries `ReferralInvite` with **no program/owner
  filter** — the model has no owner scope (boundary is via program) — selecting and
  `lockForUpdate()`-ing every owner's due invites inside each owner's transaction.
  The loop then calls `$guard->invite(..., true)` at `:95`;
  `ReferralProgramGuard.php:22,50-61` uses `OwnerWriteGuard::findOrFailForOwner` /
  `firstOrFail`, which throws `ModelNotFoundException` for the first foreign invite.
  The exception escapes `DB::transaction` (whole batch rolls back) and escapes
  `handle()` (no try/catch around the `withOwner` calls at `:50/:73`).
- Failure scenario: any multi-owner install (or owner + global mix) with due invites
  under ≥2 owners: `referrals:expire-invites` crashes, expires nothing, every run,
  after briefly locking other tenants' rows. Single-owner test
  (`ReferralLifecycleTest.php:512-530`) masks it. Deterministic broken core flow +
  cross-tenant locking in a command = high.
- Minimal fix: constrain the due query to programs of the current owner context
  (e.g. `whereHas('program')` under owner scope / program-id subquery) before locking;
  wrap each invite in its own transaction so one bad row cannot roll back the batch.
- Regression test: two owners each with a due and a future invite → command exits
  success, both due invites `Expired`, futures untouched, counts correct.

### REF-07 MEDIUM — `expired_at` is a future deadline, not the expiry transition time
- Body: `CreateReferralInvite.php:140` sets `expired_at = now + expiryDays` at creation;
  migration `...000002:28` has only `expired_at` (no `expires_at`);
  `ReferralInvite.php:80` makes `expired_at` immutable once set, so the expire command
  (`:103-104`) and the reissue path (`CreateReferralInvite.php:88-91`) transition
  `status = Expired` without (and unable to) stamping when expiry actually happened.
  `isExpiredAt()` (`ReferralInvite.php:172-176`) conflates deadline-passed with status.
- Contract breached: monorepo model rules — `*_at` records the actual transition time;
  scheduled deadlines are separate (`expires_at`). Consequences are audit/semantic
  (no true expiry instant; `expired_at <= now` queries mean "deadline passed", not
  "expired at"), not direct value loss — medium.
- Minimal fix (evolve owning migration in place, per project rules): add `expires_at`
  for the deadline, write it at creation, stamp `expired_at = now` on transition to
  `Expired`, update factory/TestCase schema, docs in same pass.
- Regression test: new invite has future `expires_at` and null `expired_at`; after the
  command runs past deadline, `status = Expired` and `expired_at` equals run time.

### REF-08 MEDIUM — Qualification replay accepts a different business key
- Body: `QualifyReferral.php:56-61` — if the invite is already `Qualified`/`Rewarded`,
  the stored result is returned for **any** supplied key; it is never compared with
  `qualification_business_key`. Cross-invite key uniqueness (`:83-90`) only runs on
  first qualification. `ReferralLifecycleTest.php:212-229` explicitly expects a
  different-key replay to succeed silently.
- Failure scenario: host reports a second, distinct qualifying event (or a typo'd key)
  for an already-qualified invite and receives success + rewards, believing the new
  event was recorded; reconciliation against the stored key diverges silently.
- Minimal fix: same-key replay returns stored result; different key throws.
- Regression test: qualify with key A, replay with key B → exception, no new rewards,
  stored key still A. (Update the existing replay test accordingly.)

### REF-09 MEDIUM — Affiliate policy makes `Failed` affiliate-cash rewards unretryable
- Body: `ReferralAffiliateAwardPolicy.php:41` denies unless reward status is `Pending`,
  while `ReferralReward.php:78` explicitly allows `Failed → Fulfilled` retries and docs
  describe retrying `Failed` rewards. After any transient first-attempt failure that
  marks the reward `Failed` (e.g. affiliates briefly unavailable →
  `AffiliatesCashFulfillment.php:55-57` failed → `markFailed`), every retry re-enters
  `RecordAffiliateAward`, the policy denies on status, and the reward is stuck forever
  even though amount/currency/recipient are frozen-correct.
- Caveat: whether `RecordAffiliateAward` consults the policy before its own replay
  short-circuit lives in `packages/affiliates` (out of this child's inspected scope);
  the referrals-side policy body is as cited. If affiliates replays without policy,
  only never-created awards hit this — still a permanent dead end for transient failures.
- Minimal fix: policy allows `Pending` and `Failed` under the same exact-match checks.
- Regression test: affiliate-cash reward forced `Failed`, rail recovers → retry fulfils.

## Verified OK (false positives / sound contracts)

- **Owner guard**: `ReferralProgramGuard.php:21-29` resolves programs via
  `OwnerWriteGuard::findOrFailForOwner` (include-global from config); every action
  re-resolves program/invite/reward through the guard and locks the program row.
  Cross-owner accept/qualify/fulfil/guard access throw — test
  `ReferralLifecycleTest.php:258-284`. The guard's preliminary unscoped
  `whereHas('program')->whereKey()` reads only `program_id` internally and throws
  before returning anything cross-tenant. Token lookup uses `hash_equals` with a dummy
  hash (`:64-78`). Missing owner is not global: `CreateReferralProgram.php:67-72`
  `assertResolvedOrExplicitGlobal`.
- **Driver fail-closed**: `ReferralRewardFulfillmentRouter.php:41-46` throws on
  unknown/disabled/unavailable/invalid drivers; `ReferralsServiceProvider.php:43-76`
  registers voucher/affiliate/points adapters only when enabled **and** all public
  symbols exist; adapters re-check `class_exists` and return `failed()`;
  `NullReferralAffiliateResolver` → `AffiliatesCashFulfillment.php:34-36` fails closed.
  Manual rail requires a non-empty reference for fulfil and reverse
  (`ManualReferralFulfillment.php:19-29`); its do-nothing reverse is documented host
  duty (`04-usage.md`), unlike REF-01.
- **Single-claim & caps**: live-invite claim under `lockForUpdate` with token rotation
  for the same referrer and `ReferralAlreadyClaimedException` for others
  (`CreateReferralInvite.php:81-110`); unique `token_hash`,
  `qualification_business_key`, reward `(invite_id, side)` and `idempotency_key`
  (migrations `...000002:21,24`, `...000003:27,34`); invite cap
  (`CreateReferralInvite.php:112-123`) and monthly qualified cap
  (`QualifyReferral.php:92-106`) counted inside the program-locked transaction;
  self-invite rejected by contact hash and by referrer morph at acceptance
  (`CreateReferralInvite.php:41-43`, `AcceptReferralInvite.php:42-44`).
- **Lifecycle/frozen specs**: invite and reward transition matrices plus frozen
  reward attributes enforced in `updating` hooks (`ReferralInvite.php:73-118`,
  `ReferralReward.php:56-87`), including clearing `rewarded_at` only on
  `Rewarded → Qualified`; specs validated at program creation and re-validated then
  frozen at qualification (`CreateReferralProgram.php:57-59`,
  `QualifyReferral.php:108-134,156-186`). Fulfil/reverse are idempotent single-call
  (`FulfillReferralReward.php:41-47`, `ReverseReferralReward.php:43-49`).
- **Affiliate policy exactness** (aside from REF-09): deny-by-default, regex-keyed,
  locked reward lookup, exact amount/currency/source/qualification-key/recipient match,
  allow with min=max=frozen amount (`ReferralAffiliateAwardPolicy.php:21-67`).
- **Affiliates/voucher reversal rails** verify stored references before acting
  (conversion id + idempotency-key hash match `AffiliatesCashFulfillment.php:85-97`;
  voucher found + expire action `VouchersReferralFulfillment.php:79-101`) and fail
  closed on absence — the sound pattern REF-01 lacks.
- **Schema/config rules**: UUID PKs, `foreignUuid` without DB constraints/cascades,
  `commerce_json_column_type('referrals')` configurable JSON, no `down()`, integer
  minor units + explicit currency, app-level cascades in `booted()` deleting hooks.

## Unresolved / not inspected

- `packages/affiliates` `RecordAffiliateAward` replay-vs-policy ordering (affects
  REF-09 blast radius only) and `packages/vouchers` wallet-binding API surface for the
  REF-04 fix were not read in full; findings are grounded in referrals bodies plus the
  cited contract lines.
- `packages/filament-referrals` is a sibling child's scope; whether invite UUIDs /
  reward idempotency keys are exposed in admin (REF-05 exposure) was not inspected here.
