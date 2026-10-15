# Slice handoff — F5 (side-scoped, family-bound, atomic rewards-points rail)

Date: 2026-10-14. Implemented per `fix-plan.md` F5 on top of F4 (merges XP-01,
XP-02, REF-02, REF-03). Depends on F1 (job owner handling).

## Red evidence (pre-fix)

Four red cases, each verified failing on the pre-fix tree (stash-and-rerun):

1. Side-scoped fulfilment: the spec layer rejected `family_key`
   ("unsupported keys"), so the first two tests died at program creation.
   After the validator/creation fixes landed (incremental red→green), the
   deeper red showed: `RewardAward::count()` was **2** after the first side
   (sharer + actor candidates of the whole fact, both live) instead of 1 —
   the unscoped `dispatchSync($businessKey)` fan-out.
2. Family binding: two active programs both paying 10 → the fulfilment
   succeeded with **2** awards for the side key (one per program), the
   probe's unordered `first()` hiding the orphan.
3. Cross-side creation: a program promising 10 to the referrer and 20 to
   the referee (same or missing family) was created — illegal, since one
   program's points component has one value.
4. Spec validator: `family_key` was rejected everywhere (no key allowed
   on any driver).

Plus the form-layer red: the extended Filament create test asserted the
frozen spec carries `family_key`; with the two form files stashed the
stored spec dropped it (failed at the `toBe` on the referee spec).

## Files changed (implementation)

- `packages/rewards/src/Jobs/EvaluateRewardEntitlementJob.php` — optional
  trailing scope params `?string $onlyRecipientId = null,
  ?RewardBeneficiaryRole $onlyRole = null, ?string $onlyRecipientType = null,
  ?string $onlyFamilyKey = null`. Candidates are filtered before evaluation
  (`inScope()`: recipient id, role, `program->family_key`); recipient type =
  `$onlyRecipientType ?? $this->recipientType()` and now also flows into
  `recordMilestones()`; `uniqueId()` appends a scope suffix when any scope
  param is set, so queued scoped/unscoped dispatches never collapse.
- `packages/referrals/src/Support/ReferralRewardSpecValidator.php` —
  `family_key` is an allowed key on the `reward_points` driver only (driver
  is resolved first so other drivers reject it as unsupported), validated
  as a 1..64 slug (`^[a-z0-9][a-z0-9_-]{0,63}$``, mirroring the rewards
  family rule), and carried into the validated spec.
- `packages/referrals/src/Actions/CreateReferralProgram.php` — new
  `assertPointsFamiliesCarryOneAmount()` after both specs validate: two
  points sides with different amounts require a `family_key` on both sides,
  and may not share one family (one component, one value). F9's
  normalizer must carry the same check.
- `packages/referrals/src/Fulfillment/RewardsPointsFulfillment.php` —
  scoped named-args dispatch (`onlyRole` = side map: Referrer→`sharer`,
  Referee→`participant`, passed via the enum's string class to preserve the
  optional-dependency style; `onlyRecipientId`/`onlyRecipientType` = frozen
  beneficiary; `onlyFamilyKey` = `spec['family_key'] ?? null`). Fact gate
  now requires the frozen beneficiary type to be *in* `rewards.recipient_types`
  (the `[0] ===` first-entry constraint is gone — the type travels
  explicitly). Dispatch + probe share one nested `DB::transaction`; any
  post-dispatch failure throws out of the unit (so **no RewardAward rows
  persist** for a failed side) and is caught to return `failed()`. Probe
  adds `beneficiary_role` + (when set) `family_key` filters, `orderBy('id')`,
  and requires **exactly one** live award; strict points requires
  `is_int` on both sides and equality with the frozen spec.
- `packages/filament-referrals/.../Schemas/ReferralProgramForm.php` — new
  "Rewards family key" `TextInput` per side, visible only for the
  `reward_points` driver, with the slug regex and 64-char cap.
- `packages/filament-referrals/src/Support/ReferralProgramFormData.php` —
  `toDomain()` carries a trimmed `family_key` on the points driver;
  `toForm()` re-hydrates it (the adapter stopped dropping it).

## Tests

- `tests/src/Referrals/ReferralLifecycleTest.php` — reworked
  'fulfils rewards points only when a matching non-refused award exists' into
  'fulfils each referral side against its own role, recipient, and family'
  (two families keep 10/20 legal; phase 1 unenrolled → `Failed` with zero
  awards; phase 2 referrer side → `Fulfilled`, exactly 1 award, role
  `sharer`, family `referral-share`, 10 points; phase 3 referee side on a
  15-point family program → `Failed` 'does not match' with **zero** awards
  for the key; the unenrolled→enroll→retry flow is preserved). New
  'fulfils the referrer side only from the family-bound rewards program'
  (two active 10-point programs; only the spec-bound family awards; the
  wrong family leaves zero rows). New 'rejects differing points on a shared
  or missing rewards family' (missing-family, shared-family, distinct-family,
  equal-points cases). New 'accepts a points family slug and rejects family
  keys on other drivers' (valid slug; manual/voucher/bad-slug/empty/65-char
  rejected).
- `tests/src/FilamentReferrals/FilamentReferralsTest.php` — the create-page
  test now fills `family_key` on the points side and asserts the frozen
  spec carries it.
- `tests/src/Rewards/Ledgers/Support/RewardLedgerFixtures.php` —
  `seedVersionedProgram()` takes a trailing `?string $familyKey = null`
  (default behavior unchanged) so tests can seed family-bound programs.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Referrals` → 26 passed (127 assertions).
- `./vendor/bin/pest --parallel tests/src/Rewards` → 234 passed, 66 skipped (1300 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentReferrals` → 8 passed (54 assertions).
- `./vendor/bin/phpstan analyse packages/rewards/src packages/referrals/src packages/filament-rewards/src packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the 8 changed PHP files → PASS (1 style fix).

## Docs

- `packages/referrals/docs/01-overview.md` — spec shape gains `family_key`
  plus the one-value-per-component rule.
- `packages/referrals/docs/04-usage.md` — reward spec section documents the
  family slug and the differing-amounts rule; Rewards points section
  rewritten for scoped evaluation (side→role map), per-side families,
  explicitly carried recipient type (the `recipient_types[0]` warning line
  deleted), exactly-one live award, strict points, and the zero-award
  failure contract.
- `packages/filament-referrals/docs/04-usage.md` — the family-key form
  field.
