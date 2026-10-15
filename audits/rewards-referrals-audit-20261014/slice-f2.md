# Slice handoff — F2 (complete RewardProgramGuard coverage)

Date: 2026-10-14. Implemented per `fix-plan.md` F2 on top of F1.

## Red evidence (pre-fix)

True red proven by stashing only the seven action-side guard calls and re-running:
`tests/src/Rewards/RewardOwnerScopingTest.php` → **5 failed, 14 passed** — foreign
clawback committed lots/adjustments, foreign settle/write-off/leave/reaccept/
invalidation/credit all proceeded cross-tenant. With the guards in place:
**19 passed**. Adapter-level: `tests/src/FilamentRewards/RewardAdminAdaptTest.php`
→ 17 passed; the foreign settle table action is refused by the award-scoped
recovery table query (`ActionNotResolvableException`) before any movement — the
handler-level boundary remains the action guard (covered in
`RewardOwnerScopingTest`).

## Files changed (implementation)

- `packages/rewards/src/Support/RewardProgramGuard.php` — added
  `findEnrollmentOrFail()` (via `program_id`), `findRecoveryOrFail()` (via
  award → program), `findContributionOrFail()` (via cycle → `program_id`; null
  program_id throws `InvalidArgumentException`, fail closed),
  `assertProgramInScopeWhenExists()` (mirror of the award one, scoped existence).
- `packages/rewards/src/Actions/ClawBackAwardPointsAction.php` —
  `findAwardOrFail($award)` before the transaction + staff/admin/system role
  gate (member-role clawbacks now throw).
- `packages/rewards/src/Actions/SettleRewardRecoveryAction.php`,
  `WriteOffRewardRecoveryAction.php` — `findRecoveryOrFail($recoveryId)` before
  the lock.
- `packages/rewards/src/Actions/LeaveRewardProgramAction.php`,
  `ReacceptRewardTermsAction.php` — `findEnrollmentOrFail($enrollmentId)` before
  the lock. Signatures unchanged; hosts call under the member's owner context.
- `packages/rewards/src/Actions/InvalidateRewardMilestoneContributionAction.php`
  — `findContributionOrFail($contribution)` before the transaction.
- `packages/rewards/src/Actions/CreditRewardPointsAction.php` — after UUID format
  checks: `assertAwardInScopeWhenExists($awardId)` and
  `assertProgramInScopeWhenExists($programId)` when non-null. Dangling references
  stay allowed. Internal caller `TransitionRewardAwardAction:1264` runs inside a
  program-owner context (F1).

## Tests added

- `tests/src/Rewards/RewardOwnerScopingTest.php` — `guardCrossTenantFixture()`
  (tenant-owned program with fulfilled points award + lots, open recovery,
  milestone contribution, active enrollment) and five cross-tenant cases:
  foreign clawback + member-role clawback; foreign settle + write-off;
  foreign leave + reaccept; foreign contribution invalidation; credit bound to a
  foreign award (dangling still allowed).
- `tests/src/FilamentRewards/RewardAdminAdaptTest.php` — foreign settle table
  action on owner A's recovery is refused with no movement.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Rewards` → 227 passed, 66 skipped (1256 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentRewards` → 17 passed (125 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src --level=6` → No errors.
- `./vendor/bin/pint` on 8 changed files → PASS.

## Docs

- `packages/rewards/docs/99-troubleshooting.md` — guard list extended
  (enrollment/recovery/contribution), member-role restrictions, owner-disabled
  degradation note. `filament-rewards/docs/99-troubleshooting.md` already
  promises the contract — untouched, per plan.
