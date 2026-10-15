# Slice handoff — F3 (role-scoped attribution + preview sandbox)

Date: 2026-10-14. Implemented per `fix-plan.md` F3 on top of F2 (merges ATTR-CAND,
ATTR-ENGINE, ATTR-MILESTONE, ATTR-TRANSITION, filament-rewards FR-1, Staff-candidate
collapse, milestone award-role hardcode).

## Red evidence (pre-fix)

Eight targeted red cases, each failing on the pre-fix tree for its own semantic
reason (not fixture-stage noise): preview fixtures rejected `beneficiary_role` with
`Unknown fixture key` (both boundary and preview suites); explicit evaluation of an
unattributed fact froze a `Rejected` award instead of awarding an actor-bound
role; candidate fan-out still emitted a `Sharer` candidate and collapsed
`Staff` to `Participant`; milestone recording refused actor-bound unattributed
facts and accepted wrong-side involvement; a held participant award on an
unattributed fact froze `Rejected`; the Filament sandbox passed a `Carbon`
object where the core array contract demands an absolute timestamp string.
After the fix all pass. No stash-and-rerun was needed because every red failure
landed on the assertion itself.

## Files changed (implementation)

- `packages/rewards/src/Enums/RewardBeneficiaryRole.php` — new
  `requiresAttribution()` (true only for `Recipient`, `Sharer`), `binds()`
  (the single sharer/actor binding split: sharer-bound → sharer id,
  actor-bound → actor id, `Staff` additionally requires the fact's actor
  role), and static `actorSide()` (participant/purchaser/staff preserved,
  everything else collapses to `Participant`). The two duplicated
  `beneficiaryBinds()` match arms (`EvaluateRewardEntitlementAction:629`,
  `TransitionRewardAwardAction:939`) now delegate here — one authority.
- `packages/rewards/src/Support/RewardPreviewFixture.php` — trailing optional
  `beneficiaryRole` defaulting to `Recipient` (fail-closed; every existing
  caller/fixture keeps sharer-bound semantics).
- `packages/rewards/src/Support/RewardPreviewEngine.php` — the
  `LateAttributionRefused` gate now binds on
  `$fixture->beneficiaryRole->requiresAttribution()`; class docblock records
  unattributed as the one role-scoped invariant (self-credit, bot, invalid,
  cancelled, guest stay unconditional).
- `packages/rewards/src/Actions/PreviewRewardQualificationAction.php` —
  `fixtureFromArray()` accepts `beneficiary_role` (string value, default
  `recipient`, unknown throws); pass-through to the fixture.
- `packages/rewards/src/Actions/EvaluateRewardEntitlementAction.php` —
  fixture carries `beneficiaryRole: $role`; an explicit actor-bound
  evaluation of an unattributed fact awards instead of freezing a
  permanent rejection. `late_attribution_refused` stays outside
  `TRANSIENT_REASONS` (verified), so sharer-bound roles still freeze.
- `packages/rewards/src/Support/RewardEvaluationCandidates.php` — invalid and
  cancelled facts still yield `[]`; unattributed facts drop only the sharer
  side and keep the actor side (`actorSide()` preserves a `Staff` actor role).
- `packages/rewards/src/Actions/RecordRewardMilestoneContributionAction.php`
  — `assertFactQualifies()` takes the parsed role: attribution demanded only
  for sharer-bound roles; involvement replaced by per-role `binds()`; the
  minted milestone award carries the achieving contribution's
  `beneficiary_role` instead of a hardcoded `Recipient`. Docblock updated.
- `packages/rewards/src/Actions/TransitionRewardAwardAction.php` — frozen
  recheck fixture carries `beneficiaryRole: $award->beneficiary_role`;
  `assertMilestoneContributorsValid()` gates attribution and binding on each
  contribution's stored `contributor_role` (unknown role refuses; evidence
  now records `contributor_role` per source).
- `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/ViewRewardProgram.php`
  — sandbox speaks the core array contract (`occurred_at` ISO string,
  `verification` string) and adds the beneficiary-role `Select` (default
  `recipient`) forwarded as `beneficiary_role`. Array form kept; the
  fixture-object path stays for core callers.

## Tests added

- `tests/src/Rewards/Core/RewardQualificationBoundaryTest.php` — actor-bound
  roles (participant/purchaser/staff) earn unattributed evidence while
  sharer-bound roles keep refusing; unknown `beneficiary_role` throws.
- `tests/src/Rewards/Core/RewardPreviewTest.php` — participant-role
  unattributed preclaim fixture is eligible.
- `tests/src/Rewards/Ledgers/RewardAwardEvaluationTest.php` — unattributed
  fact awarded to an actor-bound role, no frozen rejection row; the existing
  dataset's Recipient refusal case is unchanged.
- `tests/src/Rewards/Ledgers/RewardRoleAttributionTest.php` (new) —
  candidate fan-out (actor-side only for unattributed, `Staff` preserved,
  invalid/cancelled silent); milestone actor-bound contributions count
  unattributed (award carries `participant`); sharer-bound unattributed,
  wrong-side, and staff-on-non-stactor contributions throw with no award
  written; a held participant award on an unattributed fact advances to
  approved.
- `tests/src/FilamentRewards/RewardAdminAdaptTest.php` — sandbox
  `callAction('preview_qualification')` runs clean and emits the
  `Preview:` notification (zero coverage before).

Existing milestone suites were aligned to the tightened binding: their
default fixture facts bind the sharer side, so their roles moved from
`Participant` to `Recipient` (RewardBadgeMilestoneTest, RewardCorrectionClosureTest,
RewardLedgerClosureTest, RewardOwnerScopingTest, RewardCorrectionSweepTest,
RewardLedgerRepairTest, RewardLedgerRaceTest/Worker). Behavior under test was
unchanged by that alignment.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Rewards` → 234 passed, 66 skipped (1300 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentRewards` → 18 passed (133 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src --level=6` → No errors.
- `./vendor/bin/pint` on 35 changed files → PASS.

## Docs

- `packages/rewards/docs/01-overview.md` — evaluation/attribution semantics bullet.
- `packages/rewards/docs/04-usage.md` — new "Beneficiary roles and attribution"
  section (binding table, per-surface guarantees, `beneficiary_role` fixture key).
- `packages/filament-rewards/docs/04-usage.md` — new "Qualification preview
  sandbox" section (payload contract, role select, no-enrollment verdict).
