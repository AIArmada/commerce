# Independent review + repairs

Date: 2026-10-14. An independent reviewer (fresh context, no knowledge of the
implementer's reasoning) reviewed the entire uncommitted F1–F9 diff against
`fix-plan.md`, `AGENTS.md`, and the `slice-f*.md` handoffs. This file records
what the review found, what was repaired, and what was consciously accepted.

## Review verdict (as received)

"Not ready for sign-off" — one blocking finding plus a set of non-blocking,
test-quality and doc findings. The reviewer independently confirmed PHPStan
level 6 clean on all four packages, `tests/src/Referrals` green, and the
invariant greps clean.

## Repair 1 (blocking) — F1 enumeration widening is now gated on "no ambient owner"

**Finding.** `EvaluateRewardEntitlementJob` passed `systemEnumeration: true` on
*every* dispatch, so `RewardEvaluationCandidates` widened the program scan with
`withoutGlobalScopes()` even when an owner was already resolved. The plan's F1
behaviour contract (`fix-plan.md:140`) says "ambient owner resolved: behavior
unchanged". The reviewer's consequence analysis was correct and severe: a
synchronous in-tenant dispatch — which is exactly what the F5 referrals points
rail does — enumerated **every** tenant's active programs and minted under each
program's own owner, including programs the dispatching tenant cannot read.

**Decision: the behaviour contract is authoritative; the widening is gated.**
Evidence, in order of weight:

1. The plan's behaviour contract is the acceptance criterion; the F1
   implementation bullet ("pass `systemEnumeration: true`") is mechanism, and
   its own justification ("out-of-scope candidates cannot occur") is simply
   false once an ambient owner exists.
2. The audit's own source finding for F1 is `XP-03` in `cross-package.md`,
   which is scoped entirely to the **no-ambient-owner** case: "on a real queue
   worker (no web auth, host resolver returns null — the documented production
   shape), the job throws `NoCurrentOwnerException`". It explicitly notes
   "referrals only ever calls the job synchronously inside an owner context" —
   i.e. that case works today and is not part of the verified defect.
3. The remediation's own execution contract says "smallest root-cause fix".
   Widening the ambient-owner case was collateral, not the fix.

**Change.** `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php`:

```php
$systemEnumeration = ! OwnerContext::hasOverride() && OwnerContext::resolve() === null;
```

`hasOverride() === false` distinguishes "no context at all" (queue worker,
console) from an explicit context, including the explicit-global
`withOwner(null, …)` case — so an explicit-global run stays scoped to
global-only rows, exactly as before F1. All four F1 red scenarios run under
`NullOwnerResolver` and remain green.

**Verification.** A new regression test,
`tests/src/Rewards/Ledgers/RewardFinanceLedgerTest.php` → "keeps an ambient
owner scoped instead of fanning out to other tenants", seeds a tenant-owned and
a global program, binds a `FixedOwnerResolver` for a *different* tenant, and
dispatches the job synchronously. It asserts no award exists for the
tenant-owned program. Reverting the gate (back to `systemEnumeration: true`)
makes this test fail — an award **was** minted for the foreign tenant's
program, which reproduces the reviewer's finding directly.

## Repair 2 (non-blocking) — the sweep no longer swallows contribution failures

**Finding.** `ApplyRewardSourceCorrectionsAction::invalidateContributions()`
caught `InvalidArgumentException | AuthorizationException` with an empty body,
so an orphaned owner tuple or a refused authorization left a milestone
contribution live with no trace — contradicting the class docblock's "returns
… every per-award error" and the F1 contract's "sweep records a per-award
`errors` entry and continues".

**Change.** `invalidateContributions()` now returns
`array{count: int, errors: array<string, string>}`; each failure is recorded
under `contribution:{id}` (namespaced so it cannot collide with an award UUID
key) and merged into the report's `errors` alongside the award-path entries.
The concurrent-invalidation case is recorded too, matching how the award path
already records concurrent transitions.

## Repair 3 (docs) — the fan-out claim is now qualified

`packages/rewards/docs/03-configuration.md` and `04-usage.md` gained the
"fan-out applies only when no owner is resolved" clause, and the
`EvaluateRewardEntitlementJob` class docblock says the same. With Repair 1 in
place, `packages/referrals/docs/04-usage.md`'s "synchronous dispatch keeps the
current owner context available to Rewards' owner-scoped program queries" is
accurate again and was left as-is.

Also added: a named reason on the one unscoped query F4 introduced
(`ExpireReferralInvitesCommand`), satisfying "every new unscoped entry point
needs a named reason" in the plan's final-verification note.

## Repair 4 (test gap) — configured non-default owner columns

`tests/src/Referrals/ReferralLifecycleTest.php` gained "rejects configured
non-default owner columns and status in an update payload", driving the
normalizer directly with `referrals.owner.owner_type_column` /
`owner_id_column` remapped, so the configured-column arm of F9's immutable-key
rejection is no longer untested.

## Accepted, not repaired (with reasons)

- **`CreateRewardProgram` maps any `InvalidArgumentException` to a
  `family_key` error** (`packages/filament-rewards/.../CreateRewardProgram.php`).
  Cosmetic and rated non-blocking by the review. The create form already
  validates the fields it can, and the plan's F8 did not require field-precise
  mapping. F9's attribute-tagged exception is `referrals`-scoped and cannot be
  reused by the `rewards` action without a cross-package change the plan did
  not authorise. Left as a documented cosmetic gap.
- **File-scope global helper functions** in the new test files
  (`rewardAuthzHeldAward`, `rewardAuthzInvokeAction`, `roleMilestoneProgram`,
  …). This mirrors the existing suite convention (`globalReferralProgram`,
  `filamentReferralsQualifyInvite`) and the names are role-prefixed; the risk
  is a future collision, not a current defect.
- **Untested branches flagged by the review**: F6's `catch (Throwable)` in
  `RewardsPointsFulfillment::reverse()` and F2's dangling-vs-existing program
  arm for `CreditRewardPointsAction`. Both are pre-existing-F6/F2 coverage
  gaps in code paths whose fail-closed behaviour is already exercised through
  the adjacent branch; adding tests for them is worthwhile follow-up work but
  out of scope for this repair pass.
- **Cross-owner family-less points interference.** With Repair 1 this can now
  only arise in the owner-less system path, which is the documented design
  (a system enumerator evaluating every program under its own owner). A
  family-less spec still relies on the host contract that exactly one active
  program awards a given outcome; a family-bound spec is unambiguous because
  `reward_programs.family_key` is globally unique. Documented, not changed.

## Post-repair gates

- `./vendor/bin/pest --parallel tests/src/Rewards` → 235 passed, 66 skipped (1301 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentRewards` → 29 passed (175 assertions).
- `./vendor/bin/pest --parallel tests/src/Referrals` → 34 passed (188 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentReferrals` → 11 passed (77 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/filament-rewards/src packages/referrals/src packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the 56 changed PHP files → PASS.
- `rg -n -- "constrained\(|cascadeOnDelete\(" packages/rewards/database packages/referrals/database` → no matches.
- `rg -n -- "withoutOwnerScope\(|withoutGlobalScopes?\(" packages/rewards/src packages/referrals/src` → the four sanctioned call sites only (`ExpireReferralInvitesCommand`, `RewardProgramGuard`, `RewardEvaluationCandidates`), each carrying a named reason.

Note for the parent run: `./vendor/bin/pest --parallel <pathA> <pathB>` fails
with "Too many arguments, expected arguments \"path\"" — paratest accepts a
single path, so `fix-plan.md:699-700` must be run as four separate invocations
(equivalent coverage).
