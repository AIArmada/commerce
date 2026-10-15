# Rewards core audit — packages/rewards (read-only)

Date: 2026-10-14. Scope: `packages/rewards` core only. Contracts read first:
`CONTEXT-MAP.md`, `packages/rewards/CONTEXT.md`, docs 01/03/04/99,
`.ai/rules/index.md`, `config/rewards.php`, and the commerce-support owner
contract (`OwnerContext`, `OwnerScope`, `OwnerQuery`, `OwnerWriteGuard`,
`ResolveOwnedModelOrFailAction`) as read-only reference. Every finding below
cites an inspected implementation/test body; search output was used only to
locate candidates.

Verdict summary: all four delegated claims are **substantiated**.

## Claim 1 — Unconditional `isAttributed` rejection (CONFIRMED, HIGH)

`isAttributed` on `RewardSourceFact` (`src/Support/RewardSourceFact.php:36`)
is sharer-attribution evidence, yet every consumer rejects on it without
reference to the beneficiary role. Actor-bound roles already bind correctly
elsewhere: `beneficiaryBinds()` maps `Recipient`/`Sharer` to `sharerId` and
`Participant`/`Purchaser`/`Staff` to `actorId`
(`EvaluateRewardEntitlementAction.php:629`, duplicated in
`TransitionRewardAwardAction.php:939`). An unattributed fact (e.g. a member's
own verified attendance/purchase with no sharer) can therefore bind a
Participant/Purchaser/Staff recipient legitimately, but is still refused:

- `src/Support/RewardEvaluationCandidates.php:38` returns `[]` for any
  unattributed fact, discarding the actor side built at lines 48–54. The
  queued `EvaluateRewardEntitlementJob` then never attempts actor-side
  earning. Adjacent defect: lines 49–51 map any non-Participant/Purchaser
  `actorRole` (including `Staff`) to `Participant`, so automation never emits
  a `Staff` candidate at all.
- `src/Support/RewardPreviewEngine.php:138` rejects unattributed fixtures
  unconditionally, and neither `RewardPreviewFixture` nor
  `RewardPreviewContext` carries a beneficiary role, so the engine cannot be
  role-scoped without an API change. `EvaluateRewardEntitlementAction`
  inherits this: it copies `isAttributed` into the fixture (line 444) and
  runs the engine (line 455). Because `late_attribution_refused` is absent
  from `TRANSIENT_REASONS` (lines 75–88), an explicit actor-side evaluation
  freezes a permanent rejection award via `recordRejection` (line 464); a
  later attributed replay returns the duplicate instead of earning.
- `src/Actions/RecordRewardMilestoneContributionAction.php:243` throws
  "unattributed facts never count" for every contributor role. The parsed
  `$role` (line 112) is never passed to `assertFactQualifies()` (line 200),
  whose involvement check (line 251) accepts actor **or** sharer regardless
  of the asserted role — so the role is simultaneously over-enforced
  (attribution) and under-enforced (binding).
- Award-transition rechecks repeat the error twice:
  `assertFrozenSourceQualification()` rebuilds a role-less fixture
  (`TransitionRewardAwardAction.php:716`) and re-runs the engine (line 737),
  so a held actor-side award whose fact is unattributed can never advance;
  and `assertMilestoneContributorsValid()` rejects any unattributed
  contributor at line 561 even when the recipient is the actor (line 569
  already permits actor involvement).

False positive inside this claim: the live verdict itself is correct.
`liveSourceVerdict()` (`EvaluateRewardEntitlementAction.php:596`) and the
direct transition recheck (lines 423–453) do **not** test `isAttributed`;
they rely on role-aware `beneficiaryBinds()`. The defect enters only through
the preview engine, candidates, and milestone paths.

Minimal fix: add a role-aware attribution predicate (e.g.
`RewardBeneficiaryRole::requiresAttribution()` true only for
Recipient/Sharer), thread the role through `RewardPreviewFixture`/
`RewardPreviewContext` and `PreviewRewardQualificationAction`, keep the
actor side in candidates, and gate lines above on the predicate. Existing
tests that encode the unconditional rule
(`tests/src/Rewards/Core/RewardQualificationBoundaryTest.php:90`,
`tests/src/Rewards/Ledgers/RewardAwardEvaluationTest.php:145`) must be split
by role in the same pass, and docs 01/04 updated.

## Claim 2 — Queue/console without current owner (CONFIRMED, HIGH)

`RewardProgram` is the sole `HasOwner` model (`RewardProgram.php:58`). Its
global `OwnerScope` resolves the current owner and throws
`NoCurrentOwnerException` (a `RuntimeException` via `CommerceException`)
when none is resolved and no explicit global override exists
(`OwnerScope.php:21`, `OwnerContext.php:123`). `RewardProgramGuard`
delegates to `OwnerWriteGuard`, which asserts the same
(`ResolveOwnedModelOrFailAction.php:37`). Neither job uses the
`OwnerContextJob` trait or carries an owner payload — both carry only a
business key — and no code wraps work in `OwnerContext::withOwner(...)`:

- `EvaluateRewardEntitlementJob.php:70` calls
  `RewardEvaluationCandidates::for()`, whose first query
  (`RewardEvaluationCandidates.php:63`, `RewardProgram::query()`) throws
  before any candidate exists. The job's `Refused:` catch (line 84) only
  handles `InvalidArgumentException`, so the job retries 3× and fails
  permanently. Even past candidates, `EvaluateRewardEntitlementAction.php:107`
  and milestone recording (`RecordRewardMilestoneContributionAction.php:89`)
  hit the same guard.
- `RecheckRewardAwardsJob` enumerates awards fine (ledger models have no
  owner scope) but its first `TransitionRewardAwardAction` call (line 117)
  throws at the guard (`TransitionRewardAwardAction.php:118`); the
  `Invalid award transition` catch (line 126) does not cover it, so no award
  settles and the contribution loop (line 138) is never reached.
- `ApplyRewardSourceCorrectionsAction` catches only
  `InvalidArgumentException` per award (line 132), so the first owned-program
  award aborts the whole sweep mid-key; the Artisan command likewise catches
  only `InvalidArgumentException` (`ApplyRewardSourceCorrectionsCommand.php:74`)
  and crashes instead of reporting per-award errors. Global programs fail too:
  explicit-global requires an override, not merely a null resolved owner.

Why tests miss this: `tests/src/TestCase.php:141` binds a
`FixedOwnerResolver` returning a real user, and fixtures seed global
programs (`RewardLedgerFixtures.php:69`), which pass the guard's
`includeGlobal: true`. Production queue/console resolvers return null.

Minimal fix (per multitenancy rules): enumerate programs/awards with an
explicit greppable `withoutGlobalScopes()`/`withoutOwnerScope()` opt-out in
the system jobs/sweep, then wrap each program's unit of work in
`OwnerContext::withOwner($owner, ...)` using
`OwnerContext::fromTypeAndIdOrFail($program->owner_type, $program->owner_id)`
(null → explicit global). Add `NoCurrentOwnerException` to the sweep's
per-award error capture as defence in depth. `ExpireRewardPointLotsCommand`
is **not** affected: it resolves only recipient-scoped lots and never touches
the program guard (verified false positive).

## Claim 3 — Program-bound mutations without `RewardProgramGuard` (CONFIRMED, CRITICAL)

`RewardProgramGuard` offers resolvers only for program/award/payout/grant/
item/appeal. Enrollment, recovery, and milestone-contribution resolvers do
not exist, and the named actions resolve rows by raw `whereKey()`:

- `ClawBackAwardPointsAction.php:84` locks any award by UUID and writes
  clawback movements/voids with no scope check and no staff/admin role gate
  (only generic `assertActor`, which accepts `member`). The consumed-value
  branch is incidentally guarded because `AppendRewardAdjustmentAction.php:76`
  asserts scope — inside the same transaction, so it rolls back — but the
  pure-claw branch (consumed = 0) commits cross-tenant.
- `SettleRewardRecoveryAction.php:64` and
  `WriteOffRewardRecoveryAction.php:47` lock any recovery by UUID. Tenant A
  staff context can settle tenant B's cash recovery, appending a budget
  `Recover` movement against B's program (capped at B's confirmed spend,
  line 107 — so no minting beyond spend, but a cross-tenant budget/state
  mutation), or write off B's receivable. `OpenRewardRecoveryAction.php:54`
  is guarded, proving the intended pattern.
- `LeaveRewardProgramAction.php:45` and `ReacceptRewardTermsAction.php:43`
  lock any enrollment by UUID with **no actor identity in the signature at
  all**. Cross-tenant leave terminates membership; cross-tenant reaccept
  appends a terms acceptance and moves `current_acceptance_id` (line 104),
  fabricating the consent that evaluation later requires. Contrast
  `EnrollRewardMemberAction.php:55` (guarded).
- `InvalidateRewardMilestoneContributionAction.php:63` resolves
  contribution/cycle unchecked (cycle carries `program_id`,
  `RewardMilestoneCycle.php:62`). The achieved-cycle branch is incidentally
  fail-closed because the nested guarded transition (line 107) throws and
  rolls back the outer transaction; the ordinary revoke/count path commits
  cross-tenant.

Minimal fix: add `findEnrollmentOrFail`, `findRecoveryOrFail` (via award),
and `findContributionOrFail` (via cycle program) to `RewardProgramGuard`,
call them before locking in each action, and add `findAwardOrFail` to
`ClawBackAwardPointsAction` (plus a staff/admin/system role gate matching
sibling reversal actions).

## Claim 4 — Additional defects

- `CreditRewardPointsAction.php:74` accepts `awardId`/`programId` with only
  UUID-format validation and never scope-checks them when they exist,
  unlike `AppendRewardAdjustmentAction`'s `assertAwardInScopeWhenExists`
  pattern. A tenant-A call can mint a spendable lot bound to tenant B's
  award/program ids (bounded per call by `max_points`, idempotent by
  operation key). Fix: assert scope when either reference exists.
- Milestone awards are always created with
  `beneficiary_role = Recipient` (`RecordRewardMilestoneContributionAction.php:581`)
  regardless of contributor roles, which feeds the transition recheck's
  role binding with the wrong role for actor-side milestones. Fix together
  with Claim 1's role threading.
- `EvaluateRewardEntitlementJob.php:98` uses the first configured recipient
  type for every candidate; multi-type hosts will mis-bind or refusal-freeze
  candidates of other types. Lower severity; document or carry the type on
  the fact/candidate.

Verified sound (false positives): settlement cannot mint budget beyond
confirmed spend (`SettleRewardRecoveryAction.php:107`); clawback dispute
creation is operation-key idempotent (`ClawBackAwardPointsAction.php:205`);
the corrections sweep delegates to guarded transition/reverse actions and
therefore fails closed rather than bypassing tenancy
(`ApplyRewardSourceCorrectionsAction.php:103`).

## Method note

Read-only audit; no tests were executed because the suite's fixed owner
resolver masks Claim 2 and current tests encode Claim 1's unconditional
rule. Each regression test in the evidence list is written to run with a
null-resolving owner resolver / explicit `OwnerContext::withOwner(...)`
scopes. Suggested verification when fixing:
`./vendor/bin/pest --parallel tests/src/Rewards` and
`./vendor/bin/phpstan analyse packages/rewards/src --level=6`.
