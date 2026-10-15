# Slice handoff — F1 (owner-context for rewards jobs/sweep/command)

Date: 2026-10-14. Implemented per `fix-plan.md` F1. Baseline: clean tree at
`e4cded62f`.

## Red evidence (pre-fix)

- `vendor/bin/pest --parallel tests/src/Rewards/Ledgers/RewardCorrectionSweepTest.php`
  → 4 failed, 4 passed. All failures: `NoCurrentOwnerException` — "AIArmada\Rewards\Models\RewardProgram requires an owner context or explicit global context." via `ResolveOwnedModelOrFailAction` / `OwnerScope`.
- `vendor/bin/pest --parallel tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php`
  → 1 failed, 9 passed. Same exception from the scoped program scan.

## Files changed (implementation)

- `packages/rewards/src/Support/RewardProgramGuard.php` — added `withProgramOwner()`:
  owner-disabled → direct callback; resolves the row `withoutGlobalScopes()`; enters
  `OwnerContext::withOwner(fromTypeAndIdOrFail(owner_type, owner_id))`; orphaned tuple →
  `InvalidArgumentException` "reference a deleted owner"; null tuple → explicit global.
- `packages/rewards/src/Support/RewardEvaluationCandidates.php` — `for($fact, $at, bool $systemEnumeration = false)`; when true (and owner enabled) the program scan widens via `withoutGlobalScopes()` (sole sanctioned unscoped query on programs).
- `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php` — `systemEnumeration: true`; each candidate's evaluate+milestones wrapped in `withProgramOwner($candidate['program'], …)`.
- `packages/rewards/src/Jobs/RecheckRewardAwardsJob.php` — each award transition wrapped per `$award->program_id`; each contribution invalidation wrapped per the cycle's `program_id`; `Invalid award transition` catch preserved.
- `packages/rewards/src/Actions/ApplyRewardSourceCorrectionsAction.php` — per-award transition/reverse wrapped in `withProgramOwner`; per-award catch widened to `InvalidArgumentException | AuthorizationException` (recorded in `errors`, sweep continues); contribution invalidations wrapped per cycle program.
- `packages/rewards/src/Commands/ApplyRewardSourceCorrectionsCommand.php` — unchanged (per plan).

## Tests added/adjusted

- `tests/src/Rewards/Ledgers/Support/RewardLedgerFixtures.php` — new `seedOwnedAndGlobalEnrolled()` (tenant-owned + global program sharing one member, fresh owner user).
- `tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php` — "fans serialized evaluation out to each program owner without an ambient owner" (`EvaluateRewardEntitlementJob::dispatchSync` under `NullOwnerResolver`).
- `tests/src/Rewards/Ledgers/RewardCorrectionSweepTest.php` — recheck job under null resolver; sweep + artisan `--keys` SUCCESS; deleted-owner contract (job throws `InvalidArgumentException` "deleted owner"; sweep records per-award error and settles the rest).

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Rewards/Ledgers/RewardCorrectionSweepTest.php` → 8 passed (34 assertions).
- `./vendor/bin/pest --parallel tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php` → 10 passed (41 assertions).
- `./vendor/bin/pest --parallel tests/src/Rewards` → 222 passed, 66 skipped (1240 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src --level=6` → No errors.
- `./vendor/bin/pint` on the 8 changed files → 8 files, 3 style issues fixed.

## Docs

- `packages/rewards/docs/04-usage.md` — corrections-sweep owner fan-out note.
- `packages/rewards/docs/99-troubleshooting.md` — new "System runs throw NoCurrentOwnerException" section.
- `packages/rewards/docs/03-configuration.md` — ownership note on system paths.

## Verification vs plan

All four F1 red scenarios from the plan pass; owner-disabled path unchanged
(existing "degrades to plain lookups" test green); orphan contract
(job throws / sweep per-award error) covered by the new deleted-owner test.

## Amendment (2026-10-14, post independent review) — see `review-repairs.md`

Two changes to this slice after the independent review:

1. **The enumeration widening is now gated on "no ambient owner".** The
   original implementation passed `systemEnumeration: true` unconditionally
   (as the plan's bullet literally said), which widened the program scan for
   *synchronous in-tenant* dispatches too and let a tenant mint awards under
   another tenant's programs. `EvaluateRewardEntitlementJob` now computes
   `$systemEnumeration = ! OwnerContext::hasOverride() && OwnerContext::resolve() === null`,
   which honours the plan's behaviour contract ("ambient owner resolved:
   behavior unchanged", `fix-plan.md:140`) and the audit's own scoping of
   `XP-03` to the no-ambient-owner case. All four F1 red scenarios run under
   `NullOwnerResolver` and stay green. New regression test:
   `RewardFinanceLedgerTest` → "keeps an ambient owner scoped instead of
   fanning out to other tenants" (fails if the gate is removed).
2. **`invalidateContributions()` no longer swallows failures.** It returns
   `array{count: int, errors: array<string, string>}`; per-contribution
   failures are recorded under `contribution:{id}` and merged into the sweep
   report's `errors`, matching the class docblock and the F1 contract.

