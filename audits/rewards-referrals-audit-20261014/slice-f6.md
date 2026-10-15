# Slice handoff — F6 (fail-closed rewards-points reversal)

Date: 2026-10-14. Implemented per `fix-plan.md` F6 on top of F5 (merges
REF-01 = XP-05). Depends on F1 (sweep owner fan-out) and F5 (scoped side
awards).

## Red evidence (pre-fix)

Two reds in `tests/src/Referrals/ReferralLifecycleTest.php`, both verified on
the pre-fix tree (the reversal then only stamped
`rewards_reversal = pending_host_corrections_sweep` and returned fulfilled):

1. Happy path: after `ReverseReferralReward`, the side's `RewardAward` was
   still `fulfilled` and the referrer's spendable balance still held the 10
   points — a terminal `Reversed` referral with live, spendable rewards
   value that could never be re-fulfilled or re-reversed.
2. Blocked case: a fulfilled award with an active reservation in unknown
   external status threw no exception — the fail-open reversal "succeeded",
   leaving the reservation unclawed and the referral incorrectly `Reversed`.

## Files changed (implementation)

- `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php` —
  `reverse()` rewritten (string-class style preserved):
  1. Symbols check (`RewardAward`, `ApplyRewardSourceCorrectionsAction`);
     missing symbols fail closed.
  2. Probe the side's live awards (business key + frozen beneficiary +
     `points` component + `beneficiary_role` + live statuses
     held/pending_review/approved/fulfilled). None live → stamp
     `rewards_reversal = verified_corrections_sweep` (+ business key) and
     return `fulfilled(...)`, as before.
  3. Live awards → run `ApplyRewardSourceCorrectionsAction::run('system',
     null, 'system', null, 200, [['business_key' => $key, 'reason' =>
     'referral-reversal', 'evidence' => [referral_reward_id, invite_id,
     side, reason]]])`. Explicit caller keys are trusted by design; the
     sweep runs each award under its own program owner (F1), so a referral
     owner differing from the rewards program owner still settles.
  4. Re-probe: `errors` non-empty, a still-live award, or any throw →
     `failed()`. `ReverseReferralReward` converts `failed()` into
     `ReferralFulfillmentException` and rolls its unit back — the referral
     stays `Fulfilled`, the pending metadata stamp never persists, and the
     reversal is retryable.
  5. Success (no live award remains — rejected/reversed directly, or
     fulfilled award reversed with recovery opened) → stamp
     `verified_corrections_sweep` and return `fulfilled(...)`.
- `liveSideAwards()` extracted as a private helper and tagged
  `@phpstan-impure` (the DB state changes between the two probes; PHPStan's
  pure-method return caching otherwise declares the re-probe unreachable).

## Tests

- `tests/src/Referrals/ReferralLifecycleTest.php` — reworked points test:
  the referrer side's rewards award is driven approved → fulfilled (a
  separate staff actor; caller own-approval is prohibited), asserted 10
  spendable before the reversal, and after `ReverseReferralReward` the award
  is `Reversed`, no live award remains for the side key, and the spendable
  balance is 0 (replaces the old metadata-only assertions). New test
  'blocks the referral reversal when the rewards award cannot be reversed':
  fulfilled award + active reservation in unknown external status + default
  null resolver → reversal throws `ReferralFulfillmentException`, the
  referral stays `Fulfilled`, the award stays `Fulfilled` with no
  `reversed_at`.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Referrals` → 27 passed (134 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/referrals/src packages/filament-rewards/src packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the changed PHP files → PASS.

## Docs

- `packages/referrals/docs/04-usage.md` — the Rewards points reversal
  paragraph now documents driven-and-verified semantics (explicit-key sweep,
  per-owner fan-out, `verified_corrections_sweep` metadata, blocked-reversal
  contract); the pending-host-contract wording is gone.
- `packages/referrals/docs/99-troubleshooting.md` — new "A points reversal
  is blocked" entry (causes, resolution, retryability).
- `packages/rewards/docs/04-usage.md` — sweep section notes that
  integrations (the referrals rail) drive explicit keys, which are trusted
  by design.
