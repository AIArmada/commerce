# Slice handoff — F8 (Filament rewards staff and Gate enforcement in handlers)

Date: 2026-10-14. Implemented per `fix-plan.md` F8 (merges FR-4 critical,
FR-3, FR-5, FR-7 high). Independent of F1–F7.

## Red evidence (pre-fix)

Proven by stashing `packages/filament-rewards/src` (`git stash push --
packages/filament-rewards/src`) and re-running the new suite: **9 failed, 2
passed**. Restored with `git stash pop`.

Red (all fail on the pre-fix tree):

1. Non-staff `ListRewardPayees` → `200`, expected `403`. `RewardPayeeResource`
   had no `canViewAny()`, and Filament's default resolves to allow-all when no
   policy exists.
2. Non-staff `CreateRewardProgram` → `200`, expected `403`.
   `RewardProgramResource` had no `canCreate()`.
3. Creating an owned program through a configured alias (`member` → `User`)
   → `ModelNotFoundException` (no row). The handler passed the config alias
   verbatim to `CreateRewardProgramAction`, whose `resolveOwner()` could not
   resolve it (`Relation::getMorphedModel('member') ?? 'member'` is not a
   class), so the UI could never create an owned program.
4. Award `approve` / `reject` / `reverse` invoked with the ability denied
   → `null` (no abort; the award was transitioned), expected `403`.
5. Award `dismiss_fraud` invoked with `dismissFraud` denied → `null`, expected
   `403`.
6. Appeal `decide` invoked with `decide` denied → `null`, expected `403`.
7. Fraud `dismiss` invoked with the steward ability revoked → `null`, expected
   `403`.

## Deviation from the plan's red contract (documented, deliberate)

Two cases the plan listed as reds are **already enforced one layer up**, so
they are kept as contract guards rather than reds:

- **Unconfigured owner alias** and **configured alias + missing record**:
  the create form's owner `Select` resolves values through the same
  `filament-rewards.owners.types` config for its option labels, so an
  unresolvable alias or a nonexistent record is rejected by form validation
  before `handleRecordCreation()` runs (observed error bag:
  `data.owner_type` "The selected owner type is invalid." and
  `data.owner_record_id` "The selected owner is invalid."). The handler
  resolution is therefore a fail-closed backstop for crafted payloads, not the
  first line of defence. Both guards assert owner-field errors and zero rows
  and pass on both trees; they are labelled in-file.

Two further corrections to the plan's premises, both vendor-verified against
Filament 5.8:

- **`callTableAction()` asserts visibility.** `callAction()` calls
  `assertActionVisible()`, and `Action::isDisabled()` folds in `isHidden()`, so
  `mountAction()` and `callMountedAction()` both refuse a hidden action. A
  Gate-denied record action therefore cannot be reached through the normal
  Livewire test helpers at all — the plan's expectation that a crafted request
  reaches the handler is not true for this Filament version.
- Consequently the handler re-checks are probed by resolving the configured
  action off the page's table and invoking its closure directly
  (`$table->getAction($name)->record($record)->data($data)->call()`) — exactly
  what `callMountedAction()` does once mounted. This is the only way to prove
  the handler aborts on its own rather than relying on `visible()`. The
  handlers remain the documented authorization boundary (and cover
  non-Livewire callers and ability revocation between render and call), but the
  probe is deliberately white-box.

Test file: `tests/src/FilamentRewards/RewardAdminAuthzTest.php` (new file, not
`RewardAdminAdaptTest.php` as the plan said). `RewardAdminAdaptTest.php`
registers a blanket `Gate::before(fn () => true)` in its `beforeEach`, and
Laravel evaluates `before` callbacks first-non-null-wins, so a denial cannot be
expressed in that file without rewriting every existing case. The new file
omits the blanket hook and sets its own actor/abilities.

## Files changed (implementation)

- `packages/filament-rewards/src/Resources/RewardPayeeResource/RewardPayeeResource.php`
  — `canViewAny(): bool => RewardAdminPresenter::staffActor() !== null`. No
  query change (payees are recipient-keyed by design and carry no owner column).
- `packages/filament-rewards/src/Resources/RewardProgramResource/RewardProgramResource.php`
  — `canCreate(): bool => RewardAdminPresenter::staffActor() !== null`.
- `packages/filament-rewards/src/Resources/RewardProgramResource/Pages/CreateRewardProgram.php`
  — `abort_unless(RewardAdminPresenter::staffActor() !== null, 403)` at the top
  of `handleRecordCreation()`; new `resolveOwner()` resolves the alias against
  `filament-rewards.owners.types`, validates the configured model class, loads
  the record with `whereKey(...)`, raises `ValidationException` on
  `owner_record_id` when unconfigured or missing, and returns the **Model**
  (or `null` for `platform`). The action therefore derives
  `getMorphClass()` as `owner_type` instead of storing the config alias.
- `packages/filament-rewards/src/Resources/RewardAwardResource/Tables/RewardAwardsTable.php`
  — new private `authorize(RewardAward, string $ability): Authenticatable`
  (staff role + `RewardAdminPresenter::canDecideAward`), threaded through
  `transition()` (now takes the ability: `approve`, `review`, `reverse`), the
  fulfilled-path branch of `reverse()`, and the `dismiss_fraud` closure
  (`dismissFraud`). Each ability matches the action's own `visible()`.
- `packages/filament-rewards/src/Resources/RewardAppealResource/Tables/RewardAppealsTable.php`
  — `decide` handler adds `abort_unless(canDecideAward($record, 'decide'), 403)`.
- `packages/filament-rewards/src/Resources/RewardFraudResource/Tables/RewardFraudTable.php`
  — `dismiss` handler adds
  `abort_unless(auth()->user()?->can('reward-fraud-steward') === true, 403)`,
  matching `visible()`.

## Tests

`tests/src/FilamentRewards/RewardAdminAuthzTest.php` — 11 tests / 42
assertions:

- non-staff payee listing → `403`.
- non-staff program creation → `403` and zero `RewardProgram` rows.
- guard: unconfigured owner alias → `owner_record_id` error, zero rows.
- guard: configured alias + missing record → `owner_record_id` error, zero rows.
- owned program via a configured alias (`member` → `User`) is created with
  `owner_type` equal to the owner's morph class and `owner_id` equal to the
  owner's key (proves the alias is not stored verbatim).
- dataset over the four award actions (`approve` / `review` / `reverse` /
  `dismissFraud`): ability denied → `403`, award still `Held`. The
  `dismiss_fraud` case seeds a suspect source so the dismissal would otherwise
  succeed.
- appeal `decide` denied → `403`, appeal status unchanged.
- fraud `dismiss` with the steward ability revoked between mount and call →
  `403`, award still `Held` (proves the staff role alone is not sufficient).

Owner context note: the owned-program test reuses the harness default owner
(`OwnerContext::resolve()`), because `HasOwner::assertOwnerMatchesCurrentContext`
requires the program's owner tuple to match the active context **and** the
Spatie team resolver (`OwnerContextTeamResolver`) reads the same context —
rebinding the resolver to a different owner silently drops the staff user's
team-scoped role and the create page 403s.

## Gates (post-fix)

- `./vendor/bin/pest --parallel tests/src/FilamentRewards` → 29 passed (175 assertions).
- `./vendor/bin/phpstan analyse packages/filament-rewards/src --level=6` → No errors.
- `./vendor/bin/pint` on the 6 changed src files + the new test file → PASS.

## Docs

- `packages/filament-rewards/docs/03-configuration.md` — Staff section gains the
  staff boundary (payees, program creation) and the ability table
  (`approve` / `review` / `reverse` / `dismissFraud` / `decide` /
  `reward-fraud-steward`); Owners section documents server-side alias
  resolution and morph-class storage.
- `packages/filament-rewards/docs/04-usage.md` — Programs section documents the
  staff-only create page and the server-side owner re-resolution.
- `packages/filament-rewards/docs/99-troubleshooting.md` — new "A decision
  returns 403 for a user who can see the button" and "Program creation fails on
  `owner_record_id`" entries.
