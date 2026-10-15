# Fix plan — rewards / referrals severe audit findings (2026-10-14)

Read-only triage of `rewards-core.md`, `filament-rewards.md`, `referrals-core.md`,
`filament-referrals.md`, `cross-package.md` in this directory. Every critical/high
claim below was re-inspected in the real code and a falsification attempt was made
(sibling guard, model hook, Filament framework enforcement, transaction boundary).
All severe claims survive; none was falsified outright. Two were narrowed
(GUARD-CREDIT, FR-7 below) and severities were harmonized where reports disagreed.

Settled product direction applied throughout: **actor-bound participation
(Participant/Purchaser/Staff) must be able to earn from an unattributed fact;
sharer-bound credit (Recipient/Sharer) still requires attribution.** This is not
re-opened as a product decision anywhere in this plan.

Severity rubric: critical = cross-tenant mutation/disclosure, money/points
mint/spend, fail-open reversal, authorization bypass. High = deterministic broken
core flow, unsafe missing-owner job/command behavior, unlimited redeemable value,
write action bypassing the package guard.

## Global rules for every group

- Red regression test first: add/adjust the named test, watch it fail on current
  code, then implement. Existing tests that encode the defective behavior are
  listed per group and must be changed in the same pass (never skipped/deselected).
- Verify per touched package only: `./vendor/bin/pest --parallel tests/src/<Pkg>`
  and `./vendor/bin/phpstan analyse packages/<pkg>/src --level=6`; Pint only on
  changed files. Pipe test output `2>&1 | tee /tmp/out.txt`.
- Docs change in the same pass as public behavior/config (listed per group).
- Project invariants: no DB FK constraints/cascades, migrations evolve in place
  with no `down()`, JSON columns via `json_column_type`, money = integer minor
  units + currency, Filament packages are adapters (domain fixes land in core),
  owner is a security boundary (explicit, greppable opt-outs only).

## Merges, severity harmonization, false positives

Duplicates merged (one fix, one test surface):

- `GUARD-RECOVERY` (rewards-core) = `FR-6` (filament-rewards) → F2. Fix in core.
- `JOB-EVAL-OWNER` = `XP-03`; `JOB-RECHECK-OWNER` + `SWEEP-OWNER` = `XP-04` → F1.
- `REF-01` = `XP-05` → F6. Severity **critical** adopted (fail-open reversal per
  rubric; cross-package report called it high).
- `ATTR-CAND/ENGINE/MILESTONE/TRANSITION` are one root cause (role-less
  attribution gate) → F3, together with filament-rewards `FR-1` (same preview
  surface) and two unrated rewards-core extras in the same code: Staff actorRole
  collapsed to Participant (`RewardEvaluationCandidates.php:49`) and milestone
  awards hardcoded `beneficiary_role=Recipient`
  (`RecordRewardMilestoneContributionAction.php:581`).
- `XP-01` (double mint) and `REF-02` (orphan on mismatch) are distinct defects
  in one adapter flow → F5 with separate contracts. `REF-03` (medium, skippable
  points check) is subsumed by F5's strict-points contract.
- filament-referrals `FR-03` (medium, arbitrary Edit status) is subsumed by F9.

Narrowed after falsification attempts (still FIX):

- `GUARD-CREDIT` (high): `CreditRewardPointsAction` is a recipient-keyed ledger
  primitive by design (cross-package FP #3 stands for recipient/family args).
  The verified defect is narrower: a lot can be *bound to a foreign award/program
  id*, polluting that award's clawback accounting. Fix mirrors the sibling
  `assertAwardInScopeWhenExists` pattern only; do not owner-gate the primitive.
- `FR-7` (filament-rewards, high): verified against vendor Filament —
  `InteractsWithActions::mountAction/callMountedAction` check `isDisabled()` and
  `isAuthorized()` only when `authorize()` is set; `visible()` is never enforced
  server-side. Hidden award actions are directly callable. Claim stands.
- `FR-4` (filament-rewards, critical) and `FR-3` (high) bite only on role-aware
  hosts: on role-less hosts `staffActor()` returns any authenticated panel user
  by documented design (`filament-rewards/docs/03-configuration.md:37`). Kept
  severe because the adapter's own docs promise staff gating; tests must use a
  role-aware user double (see F8).

Verified false positives (do not fix; re-verified in this triage):

- `liveSourceVerdict()`/`beneficiaryBinds()` are role-aware and never test
  `isAttributed` (`EvaluateRewardEntitlementAction.php:596,629`). The attribution
  defect enters only via preview engine, candidates, milestone paths (F3).
- `ExpireRewardPointLotsCommand.php:34` is recipient-scope keyed and ownerless-safe.
- Recovery settlement cannot mint budget beyond confirmed spend
  (`SettleRewardRecoveryAction.php:107`). Its defect is scope, not amount (F2).
- `RewardCapCounter.consumed` is monotonic (`EvaluateRewardEntitlementAction.php:728`);
  reversal cannot restore quota. Conservative, not a bypass.
- `family_key` is globally unique (`2001_05_01_000001…:18`), so recipient-keyed
  point/payee scopes cannot collide across owners.
- Null reader/readiness/financial/correction defaults fail closed
  (`RewardsServiceProvider.php:54-64`); `Funded*` checkers are documented opt-in.
- Guard inclusion oracle (`RewardProgramGuard.php:46`) needs an unguessable UUID
  and splits only exception type. Not enumerable. (The include-global *write*
  inconsistency is separate — REPORT XP-06 below.)
- Referrals guard preliminary reads are program-scoped and return nothing
  cross-tenant (`ReferralProgramGuard.php:46-61,99-118`); drivers fail closed
  (`ReferralRewardFulfillmentRouter.php:41`).
- filament-referrals: table status actions are matrix-checked after re-resolution
  (`ReferralProgramsTable.php:84`); arbitrariness exists only via Edit (F9).
  Expiry labeling matches core's pre-set `expired_at` deadline (wart is REF-07).

Dependency order rationale: F1 (owner wrapping) lands before F2 (new guards)
because recheck/sweep call `Invalidate…`/`Transition…` from system contexts; F3
touches the same Candidates/Job files as F1; F5 needs F1's job and F9 needs F5's
spec validator change; F6 needs F1's sweep and F5's scoped awards.

---

## F1 — OWNER-SYSTEM: rewards jobs/sweep/command run per program owner

Merges: `JOB-EVAL-OWNER` (=XP-03), `JOB-RECHECK-OWNER`, `SWEEP-OWNER` (=XP-04). High.

### Exact files / symbols

- `packages/rewards/src/Support/RewardProgramGuard.php` — add
  `public static function withProgramOwner(RewardProgram|string $program, callable $callback): mixed`
  (new shared helper): resolves the program row via
  `RewardProgram::query()->withoutGlobalScopes()->whereKey(...)`, then
  `OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($p->owner_type, $p->owner_id), $callback)`
  (null tuple → explicit global). When `rewards.owner.enabled=false`, run callback directly.
- `packages/rewards/src/Support/RewardEvaluationCandidates.php:36,63` —
  `for(RewardSourceFact $fact, CarbonImmutable $at, bool $systemEnumeration = false)`;
  when true and owner enabled, program query uses `->withoutGlobalScopes()`
  (greppable opt-out, this file only). Sole caller is the job.
- `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php:63-96` — pass
  `systemEnumeration: true`; wrap each candidate's evaluate+milestones unit in
  `RewardProgramGuard::withProgramOwner($candidate['program'], …)`. If an owner is
  already resolved, `withOwner` nesting with the program's own owner is a no-op
  change for in-scope candidates; out-of-scope candidates cannot occur because
  enumeration without scopes only widens to programs whose owner is then entered.
- `packages/rewards/src/Jobs/RecheckRewardAwardsJob.php:112-150` — wrap each
  award transition and each contribution invalidation in
  `withProgramOwner((string) $award->program_id, …)` (contribution: cycle's
  `program_id`). Keep the `Invalid award transition` catch.
- `packages/rewards/src/Actions/ApplyRewardSourceCorrectionsAction.php:93-138` —
  wrap per-award work in `withProgramOwner`; widen per-award catch to
  `InvalidArgumentException|AuthorizationException` (record in `errors`, continue).
- `packages/rewards/src/Commands/ApplyRewardSourceCorrectionsCommand.php:74` — no
  owner iteration needed once the action wraps per award; keep existing catch.

Do **not** wrap whole jobs/sweep in `OwnerBatchRunner::run()`: its callback
enumerates cross-owner rows (foreign awards would throw per owner) and its
`reduce()` cannot merge the sweep's list-shaped report (verified
`OwnerBatchRunner.php:178-195`). Per-unit wrapping is the contract.

### Behavior contract

- `owner.enabled=false` or ambient owner resolved: behavior unchanged.
- No ambient owner (queue/console, null resolver): every active program is
  evaluated under its own owner; global programs under explicit-global context.
  `NoCurrentOwnerException` never escapes a job or the sweep.
- Orphaned owner tuple (owner row deleted): jobs throw (retry/dead-letter is the
  visible signal for data corruption); sweep records a per-award `errors` entry
  and continues. This asymmetry is intentional.

### Red regression tests first

In `tests/src/Rewards/Ledgers/RewardCorrectionSweepTest.php` and
`tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php`: bind
`NullOwnerResolver` (`app()->instance(OwnerResolverInterface::class, …)`, overriding
`tests/src/TestCase.php:149`'s `FixedOwnerResolver`) and clear any OwnerContext
override, then: (1) serialized `EvaluateRewardEntitlementJob` awards tenant-owned
and global programs, each only under its owner; (2) `RecheckRewardAwardsJob`
rejects/reverses an owned program's awards; (3) sweep + artisan
`rewards:apply-source-corrections --keys=…` exit `SUCCESS` and report the owned
award settled. All three throw `NoCurrentOwnerException` today.

### Docs

`packages/rewards/docs/04-usage.md` (Corrections sweep: system runs fan out per
program owner, no ambient owner needed), `99-troubleshooting.md` (cross-tenant
section), `03-configuration.md` (Ownership note).

## F2 — GUARD-REWARDS: complete `RewardProgramGuard` coverage

Merges: `GUARD-CLAWBACK` (critical), `GUARD-RECOVERY` = filament-rewards `FR-6`
(critical), `GUARD-ENROLL` (critical), `GUARD-INVALIDATE` (high), `GUARD-CREDIT`
(high, narrowed above). Depends on F1 (system callers of `Invalidate…`).

### Exact files / symbols

- `packages/rewards/src/Support/RewardProgramGuard.php` — add
  `findEnrollmentOrFail(RewardEnrollment|string)` (via `program_id`),
  `findRecoveryOrFail(RewardRecovery|string)` (via award → program),
  `findContributionOrFail(RewardMilestoneContribution|string)` (via cycle →
  `program_id`; null `program_id` throws `InvalidArgumentException`, fail closed),
  `assertProgramInScopeWhenExists(string $programId)` (mirror of the award one).
- `Actions/ClawBackAwardPointsAction.php:84` — `findAwardOrFail($award)` before
  the transaction (pattern: `OpenRewardRecoveryAction.php:54`) plus
  staff/admin/system role gate (pattern: `ReverseRewardFulfillmentAction.php:64`).
- `Actions/SettleRewardRecoveryAction.php:64`, `Actions/WriteOffRewardRecoveryAction.php:47`
  — `findRecoveryOrFail(...)` before locking.
- `Actions/LeaveRewardProgramAction.php:45`, `Actions/ReacceptRewardTermsAction.php:43`
  — `findEnrollmentOrFail(...)` before locking. Signatures unchanged (no actor
  params added); hosts call under the member's owner context.
- `Actions/InvalidateRewardMilestoneContributionAction.php:63` —
  `findContributionOrFail(...)` before the transaction. This also closes the
  ordinary revoke path; the achieved-cycle branch was already incidentally
  fail-closed via the nested guarded transition.
- `Actions/CreditRewardPointsAction.php:74` — after UUID format checks:
  `assertAwardInScopeWhenExists($awardId)` / `assertProgramInScopeWhenExists($programId)`
  when non-null. Dangling (nonexistent) references stay allowed, mirroring
  `AppendRewardAdjustmentAction.php:76`. Internal caller
  `TransitionRewardAwardAction.php:1264` is already guarded and, after F1,
  always runs inside a program-owner context.

### Behavior contract

Every program-bound write action throws `AuthorizationException` (via
`OwnerWriteGuard`) for a row whose program is outside the current owner scope,
before any lock or mutation. Member-role actors can never claw back. With
`owner.enabled=false` the guard degrades to plain existence lookup (existing
`findProgramOrFail` behavior) — single-tenant hosts unchanged.

### Red regression tests first

Extend `tests/src/Rewards/RewardOwnerScopingTest.php` (cross-tenant pattern per
multitenancy rules): owner B context → foreign pure clawback (consumed = 0 branch,
the one that commits today) throws and lots unchanged; member-role clawback on own
award throws; foreign settle and write-off throw with no budget movement; foreign
leave/reaccept throw (no acceptance row created, `current_acceptance_id` intact);
foreign contribution invalidation throws, count unchanged; credit with existing
foreign `awardId` throws and creates no lot, credit with dangling `awardId` still
succeeds. Add the adapter-level case in
`tests/src/FilamentRewards/RewardAdminAdaptTest.php`: owner B invokes settle table
action on owner A's recovery → refused, no movement.

### Docs

`packages/rewards/docs/99-troubleshooting.md` (extend the guard list:
enrollment/recovery/contribution). `packages/filament-rewards/docs/99-troubleshooting.md`
already promises this contract — no text change needed.

## F3 — ATTR: role-scoped attribution (actor-only earning) + preview sandbox

Merges: `ATTR-CAND`, `ATTR-ENGINE`, `ATTR-MILESTONE`, `ATTR-TRANSITION` (all high),
filament-rewards `FR-1` (high), plus Staff-candidate collapse and milestone
award-role hardcode. Depends on F1 (same Candidates/Job files).

### Exact files / symbols

- `packages/rewards/src/Enums/RewardBeneficiaryRole.php` — add
  `requiresAttribution(): bool` (true only for `Recipient`, `Sharer`) as the single
  predicate every gate below uses. Reuse the existing sharer/actor binding split
  (`EvaluateRewardEntitlementAction.php:629`); do not duplicate it a third time —
  `TransitionRewardAwardAction.php:939` already duplicates it once.
- `packages/rewards/src/Support/RewardPreviewFixture.php:28` — trailing optional
  `public RewardBeneficiaryRole $beneficiaryRole = RewardBeneficiaryRole::Recipient`
  (fail-closed default preserves every existing preview caller/test). Role rides the
  fixture; `RewardPreviewContext` needs no new field (it already carries the fixture).
- `packages/rewards/src/Support/RewardPreviewEngine.php:138` — gate
  `LateAttributionRefused` on `$fixture->beneficiaryRole->requiresAttribution()`.
  Update the class docblock's "unconditional" invariant wording in the same edit.
  The self-credit gate above it stays unconditional — out of settled scope; note
  it in the docblock, do not change semantics.
- `packages/rewards/src/Actions/PreviewRewardQualificationAction.php` —
  `fixtureFromArray()` parses `beneficiary_role` (string value, default
  `recipient`, unknown throws). `handle()` signature unchanged.
- `packages/rewards/src/Actions/EvaluateRewardEntitlementAction.php:434` — pass
  `beneficiaryRole: $role` into the fixture. Explicit Participant evaluation of an
  unattributed fact then awards instead of freezing a permanent rejection at :464
  (`late_attribution_refused` stays non-transient for sharer-bound roles).
- `packages/rewards/src/Support/RewardEvaluationCandidates.php:38-54` —
  unattributed facts emit only actor-side candidates (drop sharer side instead of
  returning `[]`); invalid/cancelled still yield `[]`. Preserve `actorRole === Staff`
  as a `Staff` candidate instead of collapsing to `Participant`.
- `packages/rewards/src/Actions/RecordRewardMilestoneContributionAction.php:200,243,251,581`
  — pass parsed `$role` into `assertFactQualifies()`; gate attribution on the
  predicate; replace actor-or-sharer involvement with per-role binding (sharer-bound
  roles require `sharerId === recipientId`, actor-bound require `actorId`, `Staff`
  additionally requires `actorRole === Staff`); award creation uses
  `beneficiary_role => $role` (achieving contribution's role) instead of hardcoded
  `Recipient`. Per-contribution checks at transition remain authoritative for
  mixed-role cycles.
- `packages/rewards/src/Actions/TransitionRewardAwardAction.php:716,561` —
  frozen-recheck fixture gets `beneficiaryRole: $award->beneficiary_role`;
  `assertMilestoneContributorsValid()` gates attribution and binding on each
  contribution's stored `contributor_role`.
- `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/ViewRewardProgram.php:335-356`
  (FR-1) — pass `'occurred_at' => …->toIso8601String()` and
  `'verification' => (string) $data['verification']` (the core array contract at
  `PreviewRewardQualificationAction.php:242,254`); add a beneficiary-role `Select`
  (default `recipient`) forwarded as `beneficiary_role`. Keep the array form —
  smallest diff, and the fixture-object path stays for core callers.

### Behavior contract

Unattributed + actor-bound role = eligible everywhere (preview, explicit evaluate,
queued candidates, milestones, transition rechecks). Unattributed + sharer-bound
role = refused exactly as today, including permanent-rejection freezing on explicit
evaluate. Role-mismatched involvement (e.g. Participant claim where recipient is
only the sharer) refuses — this tightens milestone binding, previously under-enforced.

### Red regression tests first

- `tests/src/Rewards/Core/RewardQualificationBoundaryTest.php:90` and
  `RewardPreviewTest.php:266`: keep the default-role refusal assertion, add
  Participant-role unattributed → eligible. `Ledgers/RewardAwardEvaluationTest.php:145`
  dataset: keep the Recipient refusal case, add Participant awarded case (no frozen
  rejection row).
- New: unattributed fact yields Participant (and Staff) candidates but no Sharer
  candidate; milestone actor contribution counts while sharer-role unattributed
  contribution and wrong-side involvement throw; held Participant award on an
  unattributed fact advances through transition.
- `tests/src/FilamentRewards/RewardAdminAdaptTest.php`: Livewire
  `callAction('preview_qualification', …)` asserts `assertHasNoActionErrors()` and a
  `Preview:` notification (zero coverage exists today).

### Docs

`packages/rewards/docs/01-overview.md` (evaluation/attribution semantics),
`04-usage.md` (beneficiary roles), `packages/filament-rewards/docs/04-usage.md`
(preview sandbox role select).

## F4 — REFERRALS-GLOBAL + EXPIRE: global writes need explicit-global; expire per owner

Merges: filament-referrals `FR-01` (critical, config-conditional), `REF-06` (high).

### Exact files / symbols

- `packages/referrals/src/Support/ReferralProgramGuard.php:22-26` — `program()`
  resolves writes with `includeGlobal: false` unconditionally. Explicit-global
  contexts keep working because `OwnerQuery` returns global-only rows when the
  resolved owner is null under an explicit override (verified
  `commerce-support/src/Support/OwnerQuery.php:46`). Read *visibility* in Filament
  still comes from `OwnerScope` + `referrals.owner.include_global`; only the guard
  changes. All guard callers are mutation or pre-mutation resolutions
  (accept/qualify/fulfil/reverse/revoke/UI actions) — this *is* the documented
  contract (`referrals/docs/03-configuration.md:31`). Program rows were already
  fail-closed via `HasOwner::guardGlobalOwnerWrite`; invite/reward rows now match.
- `packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php:85-91`
  — constrain the due query *before* `lockForUpdate()`:
  `->whereIn('program_id', ReferralProgram::query()->select('id'))` (OwnerScope
  applies under the per-owner `withOwner(...)` entered at :50/:73, including
  explicit-global). Move `DB::transaction` inside the loop (per-invite unit) so one
  bad row cannot roll back the owner's batch. Keep the `expired_at` deadline column
  as-is (REF-07 is REPORT; do not half-fix schema here). The owner-disabled early
  path keeps working: the subquery is unscoped when scoping is disabled.

### Behavior contract

Host with `include_global=true`: tenant operators see global programs/invites/rewards
but every mutation on global-program rows throws unless wrapped in
`OwnerContext::withOwner(null, …)`. Multi-owner installs: `referrals:expire-invites`
expires each owner's (and global) due invites, locks only that owner's rows, exits
`SUCCESS`. Default config behavior is unchanged (bug was invisible single-owner).

### Red regression tests first

`tests/src/Referrals/ReferralLifecycleTest.php`: (1) `include_global=true`, tenant
context fulfil + revoke on global-program rows throw and change nothing,
explicit-global context succeeds; (2) two owners + one global program, each with a
due and a future invite → command exits success, all due `Expired`, futures
untouched (existing single-owner test at :512 keeps passing).

### Docs

`packages/referrals/docs/99-troubleshooting.md` (owner-scope section: wrap global
mutations explicitly); `03-configuration.md` already states the rule — keep.
`packages/filament-referrals/docs/99-troubleshooting.md` (action-refusal entry).

## F5 — POINTS-RAIL: side-scoped, family-bound, atomic rewards-points fulfilment

Merges: `XP-01` (critical), `XP-02` (high), `REF-02` (high), `REF-03` (medium, subsumed).
Depends on F1 (job owner handling).

### Exact files / symbols

- `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php:43` — optional scope
  params `?string $onlyRecipientId = null, ?RewardBeneficiaryRole $onlyRole = null,
  ?string $onlyRecipientType = null, ?string $onlyFamilyKey = null`; filter candidates
  before evaluating (family via `$candidate['program']->family_key`); recipient type
  = `$onlyRecipientType ?? $this->recipientType()`; include scope in `uniqueId()`
  when set (queued scoped/unscoped dispatches must not collapse into each other).
- `packages/referrals/src/Support/ReferralRewardSpecValidator.php:21` — allowed key
  `family_key` for `reward_points` only, slug `1..64` (mirror rewards family rule);
  reject on other drivers.
- `packages/referrals/src/Actions/CreateReferralProgram.php:57-59` — cross-side
  check after both specs validate: both sides `reward_points` with different
  `points` and equal-or-missing `family_key` → throw (one component has one points
  value, `RewardRuleValidator.php:297`). F9's normalizer must carry this same check.
- `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php:26-92` —
  replace whole-fact `dispatchSync($businessKey)` with scoped named-args dispatch:
  `onlyRole` = side map (Referrer→Sharer, Referee→Participant — pass the enum via
  its string class, preserving the optional-dependency style), `onlyRecipientId` =
  frozen beneficiary id, `onlyRecipientType` = frozen `beneficiary_type`,
  `onlyFamilyKey` = `spec['family_key'] ?? null`. Fact gate: beneficiary type must
  be *in* `rewards.recipient_types` (drop the `[0] ===` constraint, now that the
  type travels explicitly). Wrap dispatch+probe in `DB::transaction(...)` (nested
  savepoint inside `FulfillReferralReward`'s transaction); on any post-dispatch
  failure throw out of the nested unit, catch, and return `failed()` — no award
  rows persist (REF-02). Probe: add `beneficiary_role` and (when set) `family_key`
  filters, `orderBy('id')`, require **exactly one** live award (XP-02: no unordered
  `first()` acceptance). Strict points (REF-03): require
  `is_int($awardedPoints) && $awardedPoints === (int) $reward->points`, else fail.
- `packages/filament-referrals/.../Schemas/ReferralProgramForm.php` (rewardFields)
  and `Support/ReferralProgramFormData.php` (`toDomain`/`toForm`) — carry
  `family_key` (visible for `reward_points`) so the adapter stops dropping it.

### Behavior contract

Fulfilling one side evaluates exactly that side's (role, recipient, optional
family) — total awards across both sides equal the frozen promises. Amount
mismatch or missing/ambiguous award leaves the referral `Failed` with **zero**
RewardAward rows for the side key, and retry after host rule alignment can succeed
(the existing unenrolled→enroll→retry flow is preserved). Frozen rows without
`family_key` still fulfil when exactly one program awards.

### Red regression tests first

Rework `tests/src/Referrals/ReferralLifecycleTest.php:328-428`: assert
`RewardAward::count()` is 1 after the first side and 2 after the second with
per-award recipient/role (today: 2 and 4, probe-verified); split the 10-vs-20
spec into either equal points or two families (a differing-points same-family
program must now be rejected at creation); mismatch case asserts `Failed` **and**
zero awards for the key; two-program case asserts only the `family_key`-bound
program creates the fulfilling award.

### Docs

`packages/referrals/docs/01-overview.md` (spec shape + `family_key`),
`04-usage.md` (Rewards points: scoped evaluation, per-side families, carried
recipient type — delete the `recipient_types[0]` warning),
`packages/filament-referrals/docs/04-usage.md` (family field).

## F6 — POINTS-REVERSAL: reversal drives and verifies the rewards correction

Merges: `REF-01` = `XP-05` (critical, fail-open reversal). Depends on F1 (sweep
owner wrapping) and F5 (scoped side awards).

### Exact files / symbols

- `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php:94-104` —
  rewrite `reverse()` (string-class style preserved):
  1. Probe awards for the side business key + frozen beneficiary + `points`
     component in live statuses (`held, pending_review, approved, fulfilled`).
     None live → stamp metadata `rewards_reversal = verified_corrections_sweep`
     (+ business key) and return `fulfilled(...)` as today.
  2. Live awards exist → run
     `ApplyRewardSourceCorrectionsAction::run('system', null, 'system', null, 200,
     [['business_key' => $key, 'reason' => 'referral-reversal', 'evidence' => […]]])`
     (explicit caller keys are trusted by design,
     `ApplyRewardSourceCorrectionsAction.php:177-203`; F1 supplies per-program
     owner contexts, including when referral owner ≠ rewards program owner).
  3. Re-probe: success iff no live award remains (rejected/reversed directly, or
     fulfilled award reversed with recovery opened — sweep `recoveries` report).
     Still live, sweep reports the award under `errors`, symbols missing, or any
     throw → return `failed()`. `ReverseReferralReward.php:51-68` already converts
     `failed()` into `ReferralFulfillmentException` and rolls back — the referral
     stays `Fulfilled` and the pending metadata stamp must not persist on this path.
- No changes to `ReverseReferralReward` or the other rails (voucher/affiliate
  reversals already verify references — the sound pattern this follows).

### Behavior contract

A referral reward reaches `Reversed` only when its rewards award is verifiably
non-live (points clawed back or recovery opened). A blocked correction (e.g.
unattested active reservation on a fulfilled points award, null reservation
resolver) fails the reversal visibly and leaves both sides untouched and
retryable — never a terminal `Reversed` with spendable points, which is
unrepairable today because reversed rewards can be neither re-fulfilled nor
re-reversed.

### Red regression tests first

`tests/src/Referrals/ReferralLifecycleTest.php` points test: after reverse, assert
the `RewardAward` is no longer live **and** the beneficiary's spendable point
balance excludes it (replace the metadata-only assertions at :424-428). Blocked
case: fulfilled points award with an active reservation + default null resolver
(fixtures exist in `tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php`) →
reverse throws, referral stays `Fulfilled`, award stays live.

### Docs

`packages/referrals/docs/04-usage.md` (Rewards points reversal paragraph: driven
and verified, no pending-host contract), `99-troubleshooting.md` (new
"reversal blocked" entry); `packages/rewards/docs/04-usage.md` (sweep note:
referrals drives explicit keys).

## F7 — VOUCHER-RAIL: single-use, correctly valued, beneficiary-bound vouchers

`REF-04` (high). Independent of F1–F6.

### Exact files / symbols

- `packages/referrals/src/Support/ReferralRewardSpecValidator.php:73-88` — for the
  voucher driver: `usage_limit`/`max_uses` and `usage_limit_per_user`/`max_uses_per_user`,
  when present, must be int `1` (both alias spellings — `CreateVoucher.php:66-67`
  accepts either); when `amount_minor` is non-null, voucher `value` must equal it;
  `voucher.metadata.assignee` in a spec is rejected (adapter-owned, below).
- `packages/referrals/src/Fulfillment/VouchersReferralFulfillment.php:40-46` —
  force `usage_limit = 1`, `usage_limit_per_user = 1` (overwrite both alias keys),
  `value = amount_minor` when non-null, and
  `metadata['assignee'] = ['type' => $reward->beneficiary_type, 'id' => $reward->beneficiary_id]`
  — the marker `ResolvesVoucherAssignee` reads, enforced at validation
  (`VoucherValidator.php:52-67`) and at usage recording
  (`RecordVoucherUsage.php:64-69`). Verify the stored `beneficiary_type` is the
  morph class the validator compares against `getMorphClass()` (it is frozen from
  the beneficiary model at qualification) with a test assertion, not an assumption.
  Replay path (:27-37): an existing voucher fulfils only if assignee matches the
  frozen beneficiary and limits are `1/1`; a pre-fix unlimited or unbound voucher
  returns `failed()` (fail closed for legacy rows).
- Do not force an expiry: no duration semantics exist to derive one from, and
  single-use + assignee closes the severe value leak. `AddVoucherToWallet` is not
  needed — the assignee marker is the enforced binding and fits the
  string-class optional-dependency style.

### Behavior contract

Every referral-issued voucher is worth exactly the frozen `amount_minor`
(when promised), redeemable once, by the frozen beneficiary only. Specs asking
for anything else are rejected at program creation; already-frozen specs are
normalized by the adapter at issuance.

### Red regression tests first

No maintained referrals voucher-rail test exists today (harness gap: first extend
`tests/src/Referrals/ReferralsTestCase.php` to load vouchers migrations the way the
base TestCase loads rewards', or the rail cannot run in tests). Then in
`tests/src/Referrals/ReferralLifecycleTest.php`: unlimited/mismatched-value spec
rejected at creation; limit-less spec issues a `1/1` voucher with assignee =
beneficiary and `value = amount_minor`; validation/usage by a different user is
refused; replay returns the same code.

### Docs

`packages/referrals/docs/04-usage.md` (Vouchers: forced single-use + assignee),
`01-overview.md` (spec note).

## F8 — FILAMENT-REWARDS-AUTHZ: staff and Gate checks enforced in handlers

Merges: `FR-4` (critical), `FR-3`, `FR-5`, `FR-7` (high). Independent; `FR-6` is F2,
`FR-1` is F3, `FR-8` is REPORT (low).

### Exact files / symbols

- `packages/filament-rewards/src/Resources/RewardPayeeResource/RewardPayeeResource.php`
  — `canViewAny(): bool => RewardAdminPresenter::staffActor() !== null` (pattern:
  `RewardFraudResource.php:40`). No query change (payees are recipient-keyed by design).
- `.../RewardProgramResource/Pages/CreateRewardProgram.php:23` —
  `abort_unless(RewardAdminPresenter::staffActor() !== null, 403)` at the top of
  `handleRecordCreation()`; `RewardProgramResource::canCreate()` to match.
  Owner resolution (FR-5): `platform` → null/null; otherwise the alias must exist
  in `filament-rewards.owners.types`, load that config's model via `whereKey(...)`,
  `ValidationException` on `owner_record_id` when unconfigured or missing, and pass
  the **Model instance** to `CreateRewardProgramAction` (core derives
  `getMorphClass()` at `CreateRewardProgramAction.php:86-88`, fixing alias-verbatim
  storage). Core's morph+UUID checks (`resolveOwner`, :99-107) stay as backstop.
- `.../RewardAwardResource/Tables/RewardAwardsTable.php:160-185` — thread the
  ability through `transition()`/`reverse()` and the dismiss closure:
  `abort_unless(RewardAdminPresenter::canDecideAward($record, $ability), 403)`
  with `approve|review|reverse|dismissFraud` matching each `visible()`.
  Same for appeal `decide` (`RewardAppealsTable.php:68`) and fraud dismiss
  `can('reward-fraud-steward')` (`RewardFraudTable.php:57`). Handler checks are the
  security boundary; `visible()` remains presentation only (vendor-verified).

### Behavior contract

Role-aware hosts: non-staff panel users cannot list payees, create programs
(including global `platform` programs squatting a `family_key`), or decide
awards/appeals beyond their Gate abilities — by direct Livewire invocation as
well as UI. Submitted owner aliases/ids are re-validated server-side; orphan or
victim-owned programs cannot be created from the UI. Hosts must define the
`approve|review|reverse|dismissFraud|decide` abilities (or a `Gate::before`) —
already the de-facto requirement since `visible()` consults them; document it.
Role-less hosts unchanged (documented all-panel-users-are-staff boundary).

### Red regression tests first

`tests/src/FilamentRewards/RewardAdminAdaptTest.php`. Harness prerequisite: the
stock test user has no `hasAnyRole()`, so `staffActor()` always returns it —
tests must authenticate a double exposing `hasAnyRole()`/`hasRole()` with
configurable results, and drop the blanket `Gate::before(fn () => true)` (:59) for
these cases. Red cases: non-staff `ListRewardPayees` forbidden; non-staff create →
403 and zero `RewardProgram` rows; unconfigured alias and valid-alias+random-UUID
creates → form errors, zero rows; staff with `Gate::define('approve', fn () => false)`
calling the approve table action → 403, award unchanged.

### Docs

`packages/filament-rewards/docs/03-configuration.md` (Staff: Gate abilities the
host must define), `04-usage.md` (create resolves owners server-side).

## F9 — REFERRAL-UPDATE: core update action; Edit delegates and cannot change status

filament-referrals `FR-02` (high), subsumes `FR-03` (medium). Depends on F5
(spec validator + cross-side check must live in the shared normalizer).

### Exact files / symbols

- New `packages/referrals/src/Actions/UpdateReferralProgram.php` —
  `handle(ReferralProgram|string $program, array $attributes): ReferralProgram`,
  guard-resolves with `lock: true` inside `DB::transaction`. To stop create/edit
  drift (the root cause), extract Create's normalization into
  `packages/referrals/src/Support/ReferralProgramAttributeNormalizer.php`
  (name/outcome non-empty, currency `/^[A-Z]{3}$/`, slugified non-empty slug,
  expiry/caps via `nullableNonNegativeInt`, spec validation, F5 cross-side check,
  merged `starts_at < ends_at`) and call it from both actions.
  Updatable: `name, slug, qualifier_outcome, invite_expiry_days,
  max_invites_per_referrer, max_qualified_per_referrer_per_month, referrer_reward,
  referee_reward, currency, starts_at, ends_at, metadata`. Present `status` or
  owner-tuple keys → `InvalidArgumentException` (status moves only through the
  table transition actions, which already re-check the matrix at
  `ReferralProgramsTable.php:84`; owner tuples are immutable). Slug has no unique
  index (migration `…000001:30`) — match Create, add no uniqueness rule.
  Already-frozen reward rows are untouched by spec edits (existing lifecycle rule).
- `packages/filament-referrals/.../Pages/EditReferralProgram.php:45` —
  `handleRecordUpdate()` delegates to `UpdateReferralProgram::run($record, $data)`,
  mapping `InvalidArgumentException` → `ValidationException`. Direct
  `fill()->save()` path deleted.
- `.../Schemas/ReferralProgramForm.php:30` — status `Select` disabled and
  non-dehydrated on the edit operation (stays for create, which already delegates
  to core).

### Behavior contract

An edit with `currency = "!!!"`, `ends_at <= starts_at`, or an empty-slug name is
rejected at save with the record unchanged — identical rules to creation. An
Archived program cannot return to Active through Edit (including tampered
payloads); lifecycle changes happen only via the matrix-checked table actions.

### Red regression tests first

Core cases in `tests/src/Referrals/ReferralLifecycleTest.php` and Livewire cases in
`tests/src/FilamentReferrals/FilamentReferralsTest.php` (no Edit coverage exists):
bad currency and inverted window rejected with stored program unchanged; archived
program edit cannot reactivate; valid edit persists through the core action.

### Docs

`packages/filament-referrals/docs/01-overview.md` and `packages/filament-referrals/CONTEXT.md`
(remove "core has no update Action" statements), `04-usage.md` (edits delegate;
status via list actions), `packages/referrals/docs/04-usage.md` (program updates
never rewrite frozen reward rows).

---

## REPORT-ONLY — not in the severe fix pass

Each item: severity + concrete blocker. "Below severe threshold" items are real
defects; they are excluded because authorization covers verified severe findings,
and several need a design/contract decision no audit can settle.

- `XP-06` MEDIUM — `RewardProgramGuard.php:50` hardcodes `includeGlobal: true`
  vs config default false. Blocker: product semantics. Unlike referrals (F4,
  where docs explicitly forbid tenant global writes), rewards docs describe global
  programs as platform programs tenants participate in; choosing "honor config"
  vs "writes need explicit-global" changes who can enroll/earn in platform
  programs and interacts with F5 fulfilment through global programs. Needs a host
  contract decision before code.
- `FR-2` MEDIUM (filament-rewards) — preview has no member picker, so
  `require_enrollment` programs always preview unenrolled. Blocker: medium
  sandbox fidelity; core already accepts `$member`
  (`PreviewRewardQualificationAction.php:53`) but the adapter member-source UX
  (which recipient types, search) is a design task, not a severe fix. F3 ships
  the role select only.
- `REF-05` MEDIUM — voucher code deterministic from invite UUID
  (`VouchersReferralFulfillment.php:24`). Blocker: after F7, a precomputed code
  has no value to anyone but the assignee, so severity drops to defense-in-depth;
  randomizing the code requires replacing code-based replay lookup with
  metadata-key lookup — a separate compatibility design for already-issued codes.
- `REF-07` MEDIUM — `expired_at` stores the deadline, no `expires_at`
  (`CreateReferralInvite.php:140`). Blocker: schema evolution in the owning
  migration plus model immutability hook, factory, and the maintained-test hand
  schema (XP-09) must move together; audit-semantics only, no value loss. F4
  deliberately keeps the column meaning stable.
- `REF-08` MEDIUM — qualification replay accepts a different business key
  (`QualifyReferral.php:56`). Blocker: changes the documented replay contract and
  an existing test demands silent success (`ReferralLifecycleTest.php:212`); no
  double-mint (rewards freeze once), so it is a host-facing breaking-behavior
  decision, not a severe fix.
- `REF-09` MEDIUM — affiliate policy denies `Failed` retries
  (`ReferralAffiliateAwardPolicy.php:41`). Blocker: blast radius unverified —
  whether `RecordAffiliateAward` consults the policy before its replay
  short-circuit lives in `packages/affiliates`, outside this audit's authorized
  scope. Needs an affiliates read to place the fix correctly.
- `FR-04` MEDIUM (filament-referrals) — manual rewards cannot be fulfilled from
  Filament (no reference input). Blocker: medium; core contract is sound and the
  failure is recoverable (`Failed` + host sets metadata), the modal is adapter UX.
- `FR-05` MEDIUM (filament-referrals) — unlocked last-write-wins program writes.
  Blocker: F9's locked update action serializes saves but stale-form overwrite
  across requests still needs an `updated_at` optimistic-lock carried in form
  state — separate design; not severe (same-tenant operators only).
- `RC-EXTRA-TYPE` MEDIUM (rewards-core Claim 4) — generic
  `EvaluateRewardEntitlementJob::recipientType()` uses `recipient_types[0]` for
  every candidate. Blocker: `RewardSourceFact` carries no recipient-type fields;
  fixing the generic path means a breaking host-reader DTO change. F5 removes the
  referrals impact by carrying the type explicitly.
- `XP-07` LOW — docs show nonexistent `rewards:expire-point-lots --actor` options
  (`rewards/docs/04-usage.md:99` vs signature `--as-of` only). Docs-only, low.
- `XP-08` LOW — composer `suggest` promises adapters that do not exist
  (`rewards/composer.json:18`, `filament-rewards/composer.json:17`). Wording, low.
- `XP-09` LOW — referrals test fallback schema drifts from migrations
  (`ReferralsTestCase.php:118,167`). Test-infra; schedule with REF-07 (same file).
- `FR-8` LOW (filament-rewards) — preview handler lacks in-handler staff abort.
  Read-only, route-bound, `visible()`-gated probing only. Low.
- `FR-06` LOW (filament-referrals) — status action surfaces guard failures as 500
  instead of a notification. Already fail-closed; UX only.
- `FR-07` LOW (filament-referrals) — relation managers omit
  `VerifiesRelationManagerOwnerContext`. Data verified safe without it.
- `FR-08` LOW (filament-referrals) — program view hides promised amount/points.
  Display only; operators use Edit today.

## Final verification (after all groups)

```bash
./vendor/bin/pest --parallel tests/src/Rewards tests/src/FilamentRewards 2>&1 | tee /tmp/out.txt
./vendor/bin/pest --parallel tests/src/Referrals tests/src/FilamentReferrals 2>&1 | tee /tmp/out.txt
./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src packages/referrals/src packages/filament-referrals/src --level=6
./vendor/bin/pint <changed files only>
rg -n -- "constrained\(|cascadeOnDelete\(" packages/rewards/database packages/referrals/database
rg -n -- "withoutOwnerScope\(|withoutGlobalScopes?\(" packages/rewards/src packages/referrals/src
```

The last grep must show only the greppable opt-outs introduced by F1
(`RewardEvaluationCandidates`, `RewardProgramGuard::withProgramOwner`) plus
pre-existing sanctioned uses — every new unscoped entry point needs a named reason.


