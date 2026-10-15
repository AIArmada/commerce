# Luna loop 1 audit

**Verdict: CLEAN.** No remaining in-scope defect was found. All four package suites pass; PHPStan passes at level 6 in debug mode because the normal parallel worker could not open a TCP socket in this sandbox.

## Route and interruption reconciliation

- Run 1 stopped during an approximately one-hour network-reconnection hang. Run 2 (`run2-kie`) stopped on an orchestrator route switch. This execution resumed the same loop after the provider/route change; it preserved the existing F1–F9 tree and made no commit, stash, reset, clean, checkout, or restore.
- At entry, `git status --short` and the current diff showed the F1–F9 implementation plus partial loop-1 edits. `.workbuddy-ai/` was already untracked and was left untouched.
- Re-read the partial C1/C3 changes and ran `./vendor/bin/pest --parallel tests/src/Referrals/ReferralLifecycleTest.php`: 34 passed, 194 assertions, exit 0. The C1 pre-fix evidence in `/tmp/luna-c1-red.txt` shows the unrelated contribution sweep error caused reversal to fail. The C3 test's interrupted namespace/import debugging was resolved; the current test imports `Mockery` and `RuntimeException`, asserts failed reversal leaves the reward and award Fulfilled with no success metadata, then retries successfully.
- The focused C4 test now passes (1 passed, 3 assertions, exit 0). The focused owner-disabled expiry test passes (1 passed, 3 assertions, exit 0). The final Referrals rerun after formatting and added reversal/owner-disabled assertions passes (38 passed, 217 assertions, exit 0).
- `codebase-memory-mcp` graph access was attempted, but the tool returned “requires approval” while the approval policy is `never`; scoped source reads and `rg` were used as the fallback.

## Triage ledger

| Item | Result | Evidence and disposition |
| --- | --- | --- |
| C1 — F6 reversal vs. mixed correction errors | **CONFIRMED-FIXED** | The earlier implementation treated any non-empty sweep error map as a side-award failure. `RewardsPointsFulfillment::reverse()` now checks errors against the live side award IDs and then probes for remaining live awards (`packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php:130–186`). The maintained test `requires a manual reference, fulfils only once, and returns a rewarded invite to qualified on reversal` creates a program-less orphan contribution on the same key (`tests/src/Referrals/ReferralLifecycleTest.php:318,512–566`). `/tmp/luna-c1-red.txt` records the red: 1 failed, 33 passed, 184 assertions; `ReverseReferralReward` threw because the sweep reported the unrelated contribution error. The side award now reverses, its success metadata is stamped, spendable points reach zero, and the unrelated contribution remains untouched. |
| C2 — F7 voucher replay strict integer check | **DISPROVED** | The contract reference `packages/vouchers/src/Models/Voucher.php:672–683` casts both `usage_limit` fields to integer. A real Voucher model therefore supplies `1` as an integer; unlimited `null` remains rejected by the referral replay check. No source change was warranted. |
| C3 — F6 throwing sweep catch branch | **TEST-ONLY** | The same maintained reversal test mocks `ApplyRewardSourceCorrectionsAction::handle()` to throw `RuntimeException` (`tests/src/Referrals/ReferralLifecycleTest.php:540–552`), verifies state and metadata remain unchanged, then retries. The catch at `RewardsPointsFulfillment.php:172–176` returns failure. No code defect remained after the test/import repair. |
| C4 — F8 InvalidArgumentException field mapping | **CONFIRMED-FIXED** | A model with a non-UUID key reaches the core `Owned programs require an explicit owner UUID` exception. Before the fix, the filtered test failed because the exception was attached to `family_key` and `owner_record_id` was missing. `CreateRewardProgram::validationKey()` now maps family errors to `family_key`, the UUID error to `owner_record_id`, and other invalid arguments to the form-level `data` key (`packages/filament-rewards/src/Resources/RewardProgramResource/Pages/CreateRewardProgram.php:32–96`). `./vendor/bin/pest --parallel tests/src/FilamentRewards/RewardAdminAuthzTest.php --filter='attaches an invalid configured owner UUID'` now passes (1 test, 3 assertions, exit 0); the maintained regression asserts only the owner error and zero created programs (`RewardAdminAuthzTest.php:231–253`). |
| C5 — F4 expiry scan lock outside a transaction | **CONFIRMED-FIXED** | The outer `lockForUpdate()->get()` ran before any transaction and was not load-bearing. The scan is now an owner-scoped candidate read; each invite is re-resolved, locked, and deadline/status checked inside its own transaction (`packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php:108–147`). This removes a no-op lock, so there is no behavior-red test; the existing multi-owner/global expiry and include-global suppression cases remain green (`ReferralLifecycleTest.php:1137–1268`). |
| C6 — F9 date types and partial merge | **DISPROVED** | `ReferralProgram` casts both window dates to `immutable_datetime` (`packages/referrals/src/Models/ReferralProgram.php:105–107`); `UpdateReferralProgram::currentAttributes()` uses those cast values and its `array_replace` preserves omitted fields (`packages/referrals/src/Actions/UpdateReferralProgram.php:41,70–80`). The tests cover omitted metadata preservation, explicit `null` clearing, and merged-window validation (`ReferralLifecycleTest.php:1285–1380`). No mutable Carbon path was found. |
| C7 — 66 skipped Rewards tests | **DISPROVED for F1–F9 acceptance** | Both race test files skip when their configured real-driver DB host/port refuses a socket (`tests/src/Rewards/Core/RewardCoreRaceTest.php:25`, `tests/src/Rewards/Ledgers/RewardLedgerRaceTest.php:53`). Those cases exercise cross-process publish/enrollment/evaluation/ledger contention. The F1–F9 owner, attribution, referral, Filament, and edit contracts also have non-skipped maintained tests listed below; no F1–F9 contract relies solely on a skipped case. The real-driver race behavior itself remains unverified in this environment. |
| C8 — F1/F5 tenant referral using a global Rewards program | **CONFIRMED-FIXED (documentation boundary)** | `rewards.owner.include_global` defaults to `false`; tenant dispatch excludes a global program and leaves the referral side Failed without an award. The behavior is tested at `ReferralLifecycleTest.php:739–805` and documented at `packages/referrals/docs/04-usage.md:140`. XP-06 / global program guard semantics remain unchanged and out of scope. |
| Additional F4 coverage — owner-disabled expiry | **TEST-ONLY** | Added `expires due invites across tenants when referral owner scoping is disabled` (`ReferralLifecycleTest.php:1041–1059`). It proves both tenant invites expire through the disabled-owner branch. The first probe changed config after the owner scope was booted and failed; clearing Eloquent's booted model state after the config change correctly models the production boot-time setting, after which `./vendor/bin/pest --parallel tests/src/Referrals/ReferralLifecycleTest.php --filter='expires due invites across tenants when referral owner scoping is disabled'` passed (1 test, 3 assertions, exit 0). No source defect was found. |
| Additional F2 coverage — foreign program credit reference | **TEST-ONLY** | Added foreign-program refusal/no-mutation plus dangling-program acceptance to `RewardOwnerScopingTest.php:454–518`, alongside existing foreign award and dangling award assertions. |
| Additional F9 coverage — frozen reward promises | **TEST-ONLY** | Added a qualified reward snapshot assertion showing a later program edit changes the program amount but leaves the already-qualified reward at its frozen amount (`ReferralLifecycleTest.php:1389–1413`). |

## F1–F9 requirement-to-test map

| Group | Maintained evidence reviewed |
| --- | --- |
| F1 | `RewardCorrectionSweepTest.php:254–410`: owner fan-out without ambient context, owned-award recheck, sweep command, and per-award orphan-owner error while continuing. Job ambient-owner gate and owner-scoped candidate enumeration were reviewed in `EvaluateRewardEntitlementJob` and `RewardEvaluationCandidates`. |
| F2 | `RewardOwnerScopingTest.php:369–518`: foreign clawback and member-role denial, recovery settle/writeoff, leave/reaccept, contribution invalidation, and foreign-vs-dangling credit references. |
| F3 | `RewardPreviewTest.php`, `RewardQualificationBoundaryTest.php`, and `RewardRoleAttributionTest.php:63–232`: preview and explicit evaluation boundaries, actor/sharer candidate roles, milestone binding, and transition recheck. `RewardAdminAdaptTest.php:306–334` covers the Filament preview sandbox. |
| F4 | `ReferralLifecycleTest.php:290–317,1041–1268`: owner guard, disabled-owner expiry across tenants, global-write refusal, per-owner and explicit-global expiry, future invite preservation, and include-global suppression in each write pass. |
| F5 | `ReferralLifecycleTest.php:318–510,669–854`: side-specific role/recipient/family, one live award, exact integer points, failed fulfilment with no persisted awards, family binding, and the shared-family amount rule. |
| F6 | `ReferralLifecycleTest.php:512–650`: unrelated contribution errors, thrown sweep retry, blocked award reversal, and success metadata only after verified reversal. |
| F7 | `ReferralLifecycleTest.php:857–977`: single-use voucher spec/value validation, forced issuance fields, beneficiary binding, and foreign redemption/usage refusal. `Voucher` remains read-only. |
| F8 | `RewardAdminAuthzTest.php:138–320` and `RewardAdminAdaptTest.php:205–520`: non-staff listing/create denial, owner alias-to-Model resolution, invalid owner UUID field mapping, and direct Gate-denied record handler checks. |
| F9 | `ReferralLifecycleTest.php:1285–1455` and `FilamentReferralsTest.php:265–350`: immutable status/owner, configured owner columns, merged date window, partial-update semantics, frozen promises, and Filament edit delegation. |

## Changes from this loop

- `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php` — retained the interrupted C1 correction after red/green reconciliation.
- `packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php` — removed the ineffective pre-transaction row lock.
- `packages/referrals/docs/04-usage.md` — documented the default global Rewards program boundary for referral fulfilment.
- `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/CreateRewardProgram.php` — maps actual create validation causes to the correct field or form-level error.
- `tests/src/Referrals/ReferralLifecycleTest.php` — C1/C3 regression coverage and C6/C8/F9 assertions.
- `tests/src/Rewards/RewardOwnerScopingTest.php` — F2 foreign/dangling program-reference assertions.
- `tests/src/FilamentRewards/RewardAdminAuthzTest.php` — C4 invalid-owner UUID regression coverage in the in-scope untracked F8 test file.
- This handoff file.

## Gates and self-review

| Command | Result |
| --- | --- |
| `./vendor/bin/pest --parallel tests/src/Rewards` | 235 passed, 66 skipped, 1305 assertions; exit 0. |
| `./vendor/bin/pest --parallel tests/src/FilamentRewards` | 30 passed, 178 assertions; final rerun exit 0. |
| `./vendor/bin/pest --parallel tests/src/Referrals` | 38 passed, 217 assertions; final rerun after Pint exit 0. |
| `./vendor/bin/pest --parallel tests/src/FilamentReferrals` | 11 passed, 77 assertions; exit 0. |
| `./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src packages/referrals/src packages/filament-referrals/src --level=6` | Exit 1 before analysis: sandbox denied PHPStan's TCP worker listener (`EPERM`). |
| `./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src packages/referrals/src packages/filament-referrals/src --level=6 --debug` | No errors; exit 0. This runs the analyzer without the restricted parallel worker socket. |
| `rg -n -- "constrained\(|cascadeOnDelete\(" packages/rewards/database packages/referrals/database` | No matches; exit 1 as expected. |
| `rg -n -- "withoutOwnerScope\(|withoutGlobalScopes?\(" packages/rewards/src packages/referrals/src` | Exit 0; only the five reviewed owner-enumeration/guard opt-outs were found. |
| `git diff --check` | Clean; exit 0. |

Pint was run on the exact loop-touched and previously untracked PHP files below; exit 0. The final pass reported `passed` with no changes.

```sh
./vendor/bin/pint packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php packages/filament-rewards/src/Resources/RewardProgramResource/Pages/CreateRewardProgram.php tests/src/Referrals/ReferralLifecycleTest.php tests/src/Rewards/RewardOwnerScopingTest.php tests/src/FilamentRewards/RewardAdminAuthzTest.php packages/referrals/src/Actions/UpdateReferralProgram.php packages/referrals/src/Support/ReferralProgramAttributeNormalizer.php packages/referrals/src/Exceptions/ReferralProgramAttributeException.php tests/src/Rewards/Ledgers/RewardRoleAttributionTest.php
```

Self-review found no REPORT-ONLY scope creep, no database constraints/cascades, and no test skips added. The existing `.workbuddy-ai/` untracked path was not read or changed. The worktree remains uncommitted.
