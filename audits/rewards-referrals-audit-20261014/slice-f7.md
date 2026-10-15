# Slice handoff — F7 (single-use, correctly valued, beneficiary-bound vouchers)

Date: 2026-10-14. Implemented per `fix-plan.md` F7 (REF-04). Independent of
F1–F6.

## Red evidence (pre-fix)

Two reds in `tests/src/Referrals/ReferralLifecycleTest.php`, verified on the
pre-fix tree:

1. A voucher spec asking `usage_limit = 5`, `max_uses = 2`,
   `usage_limit_per_user = 3`, or `max_uses_per_user = 0`, a `value` that
   differed from `amount_minor`, or a spec-supplied `metadata.assignee` was
   accepted at program creation (no exception).
2. The issued voucher carried `usage_limit = null` — an unlimited,
   unbound voucher for a one-member reward promise (the severe value leak).

Harness note: the plan anticipated extending `ReferralsTestCase` to load the
vouchers migrations. That turned out to be unnecessary — the base
`AIArmada\Commerce\Tests\TestCase::defineDatabaseMigrations()` already loads
`packages/vouchers/database/migrations` (Orchestra Testbench calls the hook),
and the referrals suite runs on that base. The rail ran in tests without a
harness change; `tests/database/migrations/create_vouchers_tables.php` is
not loaded.

## Files changed (implementation)

- `packages/referrals/src/Support/ReferralRewardSpecValidator.php` — in the
  voucher branch: `usage_limit`, `max_uses`, `usage_limit_per_user`, and
  `max_uses_per_user` must be int `1` when present (both alias spellings;
  message contains "single-use"); a non-null `amount_minor` requires the
  voucher `value` to equal it; a spec-supplied `voucher.metadata.assignee`
  is rejected as adapter-owned.
- `packages/referrals/src/Fulfillment/VouchersReferralFulfillment.php` —
  issuance forces `usage_limit = 1`, `max_uses = 1`,
  `usage_limit_per_user = 1`, `max_uses_per_user = 1` (both alias spellings,
  so a frozen legacy spec cannot widen the limit), forces
  `value = amount_minor` when the promise names one, and writes
  `metadata.assignee = ['type' => beneficiary_type, 'id' => beneficiary_id]`.
  The replay path fulfils only when the existing voucher carries the
  referral idempotency marker **and** is single-use **and** bound to the
  frozen beneficiary (`isSingleUse()` / `isBoundToBeneficiary()` helpers);
  an unlimited or unbound legacy voucher fails closed. No expiry is forced
  (no duration semantics exist; single-use + assignee closes the leak).

## Tests

- `tests/src/Referrals/ReferralLifecycleTest.php` — new
  'rejects voucher specs that are not single-use or are mispriced'
  (four limit spellings, a mismatched value, and a spec assignee all throw;
  an explicit 1/1 spec is accepted with `value = amount_minor`), and new
  'issues single-use beneficiary-bound vouchers and refuses foreign
  redemption': the issued voucher has `usage_limit = 1`,
  `usage_limit_per_user = 1`, `value = 2500`, and
  `metadata.assignee.type` equal to the referrer's `getMorphClass()` with
  the referrer's id (asserting the frozen beneficiary type is the morph
  class, not assuming it); replay returns the same code with exactly one
  voucher row; `ValidateVoucherCode` passes for the assignee and fails for
  another user; `RecordVoucherUsage` by another user throws
  `InvalidVoucherException`.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Referrals` → 29 passed (158 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/referrals/src packages/filament-rewards/src packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the 3 changed PHP files → PASS.

## Docs

- `packages/referrals/docs/04-usage.md` — Vouchers section documents the
  forced 1/1 limits, the assignee marker, the exact-value rule, creation
  rejection, legacy-spec normalization, and the fail-closed replay.
- `packages/referrals/docs/01-overview.md` — spec note gains the
  single-use/beneficiary-bound voucher guarantee.
