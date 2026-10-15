# Slice handoff — F4 (fail-closed global writes + per-owner expiry)

Date: 2026-10-14. Implemented per `fix-plan.md` F4 on top of F3 (merges
filament-referrals FR-01, REF-06).

## Red evidence (pre-fix)

Both reds proven on the pre-fix tree (stash-and-rerun at the end of the slice):

1. Tenant writer with `include_global=true` fulfilled and revoked
   global-program rows without throwing (no change-in-nothing either — the
   guard resolved global rows for writes). After the fix both throw
   `AuthorizationException` and leave statuses untouched; the explicit-global
   context still succeeds.
2. `referrals:expire-invites` on a seeded three-tenant install threw
   `ModelNotFoundException` from `ReferralProgramGuard::program()` mid-batch
   (`ExpireReferralInvitesCommand:95`) — the command expired rows of programs
   the current context cannot resolve. With the fixes: exit 0, every owner's
   and global's due invites `Expired`, futures untouched.

Note on red (2): the first cut of the test walked the shared Carbon test clock
inside the seed closure, so only the first owner's due invite was actually due
— the failure was real but the assertion missed the other owners. The test now
creates both invites of a pair at one frozen instant (due = one-day window
already past the command horizon; future = thirty-day window still ahead) and
advances the clock only before `Artisan::call`, so all three owners are
genuinely exercised. Verified red again after the correction (same
`ModelNotFoundException`).

## Files changed (implementation)

- `packages/referrals/src/Support/ReferralProgramGuard.php` — `program()`
  resolves writes with `includeGlobal: false` unconditionally. Explicit-global
  contexts keep working because `OwnerQuery` returns global-only rows when the
  resolved owner is null under an explicit override (verified
  `commerce-support/src/Support/OwnerQuery.php:46`). Comment records that
  `include_global` is read visibility only. All guard callers are mutation or
  pre-mutation resolutions (accept/qualify/fulfil/reverse/revoke/UI actions);
  program rows were already fail-closed via `HasOwner::guardGlobalOwnerWrite`,
  so invite/reward rows now match.
- `packages/referrals/src/Console/Commands/ExpireReferralInvitesCommand.php` —
  `expireCurrentOwner()` constrains the due scan with
  `whereIn('program_id', ReferralProgram::query()->select('id'))` BEFORE
  `lockForUpdate()`, so under the per-owner `withOwner(...)` the scan locks and
  writes only that owner's (or explicit-global's) rows. `DB::transaction` moved
  inside the loop (per-invite unit) with a guard recheck (`status`,
  `expired_at`) inside each transaction, so one bad row cannot roll back the
  owner's batch and the session holding the other rows' locks is never lost
  (MySQL has no SKIP LOCKED). Owner-disabled early path unchanged (the program
  subquery is unscoped when scoping is disabled). `expired_at` deadline column
  kept as-is (REF-07 is REPORT).

## Tests

- `tests/src/Referrals/ReferralLifecycleTest.php` — new
  'refuses tenant writes on global programs while global reads stay visible'
  (tenant sees the global program via `include_global=true`; tenant
  fulfil/revoke throw `AuthorizationException` with no status change;
  explicit-global fulfil/revoke succeed once the manual driver has
  `metadata[external_reference]`) and new
  'expires due invites per owner across tenants and the global program'
  (two owners + global, due+future each → exit 0, due `Expired`, futures
  `Pending`). Helpers `globalReferralProgram()` / `globalReferralInvite()`
  added at file scope.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Referrals` → 23 passed (105
  assertions). The existing single-owner expiry test (pre-fix :512) still
  passes.
- `./vendor/bin/phpstan analyse packages/referrals/src packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the 3 changed PHP files → PASS (1 style fix: unused
  closure import).

## Docs

- `packages/referrals/docs/99-troubleshooting.md` — new "A global-program
  mutation throws in a tenant context" section (visibility vs write authority,
  guard contract, command behavior).
- `packages/filament-referrals/docs/99-troubleshooting.md` — new "A
  global-program row is visible but its actions fail" section.
- `packages/referrals/docs/03-configuration.md` — the stated rule
  (`include_global` = read visibility, writes need explicit-global) was
  already correct; kept as-is.

## Cleanup

No leftover debug artifacts: temporary `tests/src/Referrals/F4DebugTest.php`
and root scratch `f4_debug_tmp.php` deleted.
