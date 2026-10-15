# Slice handoff — F9 (REFERRAL-UPDATE: core update action; Edit delegates and cannot change status)

Date: 2026-10-14. Implemented per `fix-plan.md` F9 (filament-referrals `FR-02`
high, subsumes `FR-03` medium). Depends on F5 (spec validator + cross-side
check must live in the shared normalizer).

## Red evidence (pre-fix)

Proven by stashing **only** the six F9 paths (F1–F8 stay in the tree, so the
run is not confounded by their reverts):

```bash
git stash push -u -m f9-red-probe -- \
  packages/referrals/src/Actions/UpdateReferralProgram.php \
  packages/referrals/src/Support/ReferralProgramAttributeNormalizer.php \
  packages/referrals/src/Exceptions/ReferralProgramAttributeException.php \
  packages/referrals/src/Actions/CreateReferralProgram.php \
  packages/filament-referrals/.../Pages/EditReferralProgram.php \
  packages/filament-referrals/.../Schemas/ReferralProgramForm.php
```

Result: **6 failed, 1 passed** (the pass is the happy-path contract guard
described below). Restored with `git stash pop`.

Red, each failing for the intended reason:

1. Core: `Class "AIArmada\Referrals\Actions\UpdateReferralProgram" not found` —
   the core package had no update Action at all (the root cause: every
   mutation rule lived inline in `CreateReferralProgram::handle()`, so the
   admin edit path could not reuse it).
2. Core: an archived program could be moved back to `Active` through the
   update payload; owner tuples could be rewritten.
3. Core: an inverted window (`ends_at` supplied alone, before the stored
   `starts_at`) was accepted.
4. Livewire: `Component has no errors.` — the edit page wrote `currency =
   "!!!"` straight to the row via `fill()->save()`. The form's own
   `minLength(3)/maxLength(3)` rules let the 3-character `!!!` through, and
   nothing downstream re-checked it.
5. Livewire: `Archived` became `Active` — the status `Select` was dehydrated
   on edit, so `fill(['status' => 'active'])` reactivated an archived program.
6. Livewire: an inverted window saved silently.

## Files changed (implementation)

- `packages/referrals/src/Support/ReferralProgramAttributeNormalizer.php`
  (new) — the single source of truth for program attribute rules, extracted
  verbatim from `CreateReferralProgram::handle()` so create and update cannot
  drift: name/outcome non-empty, currency `/^[A-Z]{3}$/`, slugified non-empty
  slug, `nullableNonNegativeInt` expiry (≥1) and caps, spec validation, the F5
  cross-side one-amount-per-family check, and the merged
  `starts_at < ends_at` window check. Returns the full attribute set to
  persist. With `forUpdate: true` it additionally rejects `status` and the
  owner-tuple keys (literal plus the configured columns).
- `packages/referrals/src/Actions/CreateReferralProgram.php` — `handle()` is
  now `normalize()` + owner resolution + `forceFill` + `save`. Behaviour is
  unchanged apart from validation now running before owner resolution.
- `packages/referrals/src/Actions/UpdateReferralProgram.php` (new) —
  `handle(ReferralProgram|string $program, array $attributes)`. Inside one
  `DB::transaction` it re-resolves the program through
  `ReferralProgramGuard::program($program, lock: true)` (writes never widen to
  global rows — F4 semantics), merges the stored values under the payload
  through the model's **casts** (arrays for the specs, `CarbonImmutable` for
  the dates), normalizes with `forUpdate: true`, and saves. Status is never
  written, so an archived program cannot return to Active here.
- `packages/referrals/src/Exceptions/ReferralProgramAttributeException.php`
  (new) — `final class ... extends InvalidArgumentException` carrying the
  offending attribute name. See "Deviation" below.
- `packages/filament-referrals/.../Pages/EditReferralProgram.php` — the direct
  `ReferralProgramGuard` + `fill()->save()` path is deleted;
  `handleRecordUpdate()` delegates to `UpdateReferralProgram::run((string)
  $record->getKey(), $data)` and maps `InvalidArgumentException` to
  `ValidationException` keyed on the offending form field.
- `packages/filament-referrals/.../Schemas/ReferralProgramForm.php` — the
  status `Select` gains
  `->disabled(fn (string $operation) => $operation === 'edit')` and
  `->dehydrated(fn (string $operation) => $operation !== 'edit')`, so it stays
  editable on create and becomes a read-only, non-submitted field on edit.

## Behaviour notes worth reviewing

- **Slug derivation on update.** `slug` is merged like every other attribute,
  so a partial edit preserves it. The one exception: when the payload supplies
  a `name` but no `slug`, the merged slug is dropped so the normalizer derives
  it from the new name exactly as creation would. That is what makes the
  plan's "an empty-slug name is rejected" true for an update, without letting
  an unrelated partial edit (e.g. only `ends_at`) silently rewrite a
  customised slug.
- **Status select on edit.** Because the field is non-dehydrated, `status`
  never reaches `handleRecordUpdate()` from the form; the action's own
  immutable-key rejection is the backstop for non-Livewire callers. Both
  layers are tested (the core test passes `status` directly; the Livewire test
  tampers with the form).
- **Contract guard.** The "persists a valid program edit" Livewire case passes
  on both trees (the pre-fix `fill()->save()` also persisted the happy path).
  It is kept as a guard that the delegation did not break normal edits, and is
  labelled as such here rather than claimed as a red.

## Deviation from the plan (documented, deliberate)

The plan said `handleRecordUpdate()` should map `InvalidArgumentException` →
`ValidationException` without specifying the error key. Mapping it to a fixed
field would show, for example, a currency error under the *Name* field, and
`Filament\Forms\Testing\TestsForms::assertHasFormErrors()` resolves keys
against the schema state path (`data.<field>`), so the error has to be attached
to a real field to be both visible and assertable.

The normalizer therefore throws `ReferralProgramAttributeException` — a
subclass of `InvalidArgumentException`, matching the existing repo convention
(`packages/affiliates/src/Exceptions/AffiliateAwardPolicyLimitException.php`,
`packages/pricing/src/Exceptions/InvalidCurrencyException.php`). The documented
core contract and every `catch (InvalidArgumentException)` / `toThrow(
InvalidArgumentException::class)` caller are unchanged. The page reads
`->attribute` to key the `ValidationException` on `data.<attribute>`, falling
back to `data.name` for immutable attributes (`status`, `owner*`) that have no
form field of their own.

## Tests

- `tests/src/Referrals/ReferralLifecycleTest.php` — 5 new tests:
  - rejects an invalid program edit (bad currency, empty-slug name, inverted
    window via a partial payload) and leaves the stored program unchanged.
  - refuses `status` and `owner_id` in an update payload, and the archived
    program stays archived.
  - applies a valid edit through the core action: a partial edit keeps the
    stored slug/currency and applies the supplied caps/dates/metadata; a
    name-only edit re-derives the slug.
  - another owner cannot update the program (`AuthorizationException` from
    `OwnerWriteGuard`, matching the F4 guard semantics).
  - rejects `status` plus configured **non-default** owner columns
    (`tenant_kind`/`tenant_key` remapped via config) when driving the
    normalizer directly — added after the independent review flagged the
    configured-column arm as untested.
- `tests/src/FilamentReferrals/FilamentReferralsTest.php` — 3 new tests:
  - invalid edit (bad currency, then inverted window) is reported on the
    `currency` / `ends_at` form field with the stored program unchanged.
  - a tampered `status = active` on an archived program is ignored while the
    other edits apply.
  - a valid edit persists through the core action.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/Referrals` → 34 passed (188 assertions).
- `./vendor/bin/pest --parallel tests/src/FilamentReferrals` → 11 passed (77 assertions).
- `./vendor/bin/phpstan analyse packages/referrals/src --level=6` → No errors.
- `./vendor/bin/phpstan analyse packages/filament-referrals/src --level=6` → No errors.
- `./vendor/bin/pint` on the 8 changed files → PASS.

## Docs

- `packages/filament-referrals/docs/01-overview.md` — the stale "the core
  package currently has no update or program status-transition Action"
  sentence replaced with the real action set and the status-via-table-actions
  rule.
- `packages/filament-referrals/CONTEXT.md` — same correction in the guardrails,
  and `UpdateReferralProgram` added to the key surfaces.
- `packages/filament-referrals/docs/04-usage.md` — edits delegate to the core
  update Action and share the creation rules; the status field is read-only and
  not submitted; status moves only through the guarded list actions.
- `packages/referrals/docs/04-usage.md` — new "Update a program" section:
  guard + row lock, partial-edit merge semantics, slug derivation, immutable
  status/owner tuple, the attribute exception, and the guarantee that editing a
  program never rewrites a promise already frozen on a qualified invite's
  reward rows.
