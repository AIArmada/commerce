# Filament Referrals Adapter Audit — 2026-10-14

Scope: `packages/filament-referrals` as adapter only; `packages/referrals` and
`packages/commerce-support` read-only as contract references. Read-only audit;
no repository files edited. Maintained suite: `./vendor/bin/pest --parallel
tests/src/FilamentReferrals` → 8 passed (54 assertions).

Verdict: queries, filters, widgets, relation data, route binding, and
invite/reward actions are owner-safe and no token hashes or decrypted contacts
are rendered. The defects concentrate on program writes: Edit (and, under one
host config, global-program mutations) bypasses core invariants because core
has no update/transition Action.

## Findings

### FR-01 CRITICAL (config-conditional) — tenant can mutate global-program invites/rewards when `include_global=true`
- Where: `packages/referrals/src/Support/ReferralProgramGuard.php:25`
  surfaced by `packages/filament-referrals/src/Resources/ReferralRewardResource/Tables/ReferralRewardActions.php:33`
  and `.../ReferralInviteResource/Tables/ReferralInvitesTable.php:81`.
- Failure: `program()` resolves writes with `includeGlobal =
  referrals.owner.include_global`. A host enabling that documented *read*
  option lets any tenant operator revoke/fulfil/reverse rows of a global
  (owner-null) program. `ReferralInvite`/`ReferralReward` carry no `HasOwner`
  save guard, so the mutation succeeds (manual rail at minimum; voucher/cash/
  points adapters may also move value), contradicting
  `packages/referrals/docs/03-configuration.md:31` ("Global writes still
  require explicit global context"). Default `include_global=false` is safe.
  Program rows themselves fail closed via `HasOwner::guardGlobalOwnerWrite`
  (`packages/commerce-support/src/Traits/HasOwner.php:167`), so only
  invite/reward mutations are exposed.
- Minimal fix (core): write resolution in `ReferralProgramGuard` uses
  `includeGlobal=false` unless `OwnerContext::isExplicitGlobal()`; keep
  include-global for reads only. Adapter needs no change once fixed.
- Regression test: with `include_global=true`, tenant fulfil on a global
  reward is refused; explicit-global context succeeds.

### FR-02 HIGH — Edit page persists invalid program fields (no core validation)
- Where: `packages/filament-referrals/src/Resources/ReferralProgramResource/Pages/EditReferralProgram.php:45`
  (`fill($data)->save()`), form at `.../Schemas/ReferralProgramForm.php:48`.
- Failure: `CreateReferralProgram` enforces 3-letter uppercase currency,
  slugified non-empty slug, and `starts_at < ends_at`
  (`packages/referrals/src/Actions/CreateReferralProgram.php:33,61,98`).
  Edit checks none of these beyond length; `ReferralProgramFormData::toDomain`
  only normalizes integers and validates reward specs. Saving currency `!!!`
  or `ends_at <= starts_at` succeeds: the program then never satisfies
  `isActiveAt()` or every `QualifyReferral` spec validation throws, with no
  operator feedback at save time.
- Minimal fix: add core `UpdateReferralProgram` Action reusing Create
  validation and call it from Edit; interim, replicate currency/date/slug
  rules in form + `toDomain`.
- Regression test: edit with `currency=!!!` and with `ends_at<=starts_at`
  is rejected and the stored program is unchanged.

### FR-03 MEDIUM — Edit form allows arbitrary status transitions, including un-archiving
- Where: `.../Schemas/ReferralProgramForm.php:30` (+ Edit save above).
- Failure: the table restricts transitions (Draft/Paused→Active,
  Active→Paused, non-Archived→Archived) and re-checks after re-resolution
  (`.../Tables/ReferralProgramsTable.php:49,88`), but the Edit `Select`
  offers all four statuses from any state. An Archived program can be set
  back to Active via Edit and immediately accepts invites again. Core defines
  no program transition machine, so the two adapter surfaces disagree.
- Minimal fix: make `status` read-only on Edit (transitions only via table
  actions) or enforce the same matrix in the update Action.
- Regression test: editing an Archived program cannot return it to Active;
  activate action still works from Draft/Paused.

### FR-04 MEDIUM — manual rewards cannot be fulfilled from Filament (missing reference input)
- Where: `.../ReferralRewardResource/Tables/ReferralRewardActions.php:25`.
- Failure: the fulfil action has no form fields, yet the manual rail requires
  `metadata[external_reference]`
  (`packages/referrals/src/Fulfillment/ManualReferralFulfillment.php:12`)
  and no Filament surface edits reward metadata. A pending manual reward
  always fails to `Failed` in the UI; the passing test pre-sets metadata in
  code (`tests/src/FilamentReferrals/FilamentReferralsTest.php:154`).
- Minimal fix: add a required reference field to the fulfil modal for the
  manual driver and persist it to metadata before calling
  `FulfillReferralReward`.
- Regression test: fulfil a fresh manual reward through the table action
  supplying only the modal reference; status becomes Fulfilled.

### FR-05 MEDIUM — program writes are unlocked last-write-wins
- Where: `.../Tables/ReferralProgramsTable.php:85`,
  `.../Pages/EditReferralProgram.php:47`.
- Failure: both re-resolve without `lock:true`, then mutate and `save()`. A
  stale Edit submit silently overwrites a concurrent pause/archive (including
  resurrecting status from stale form state) and concurrent field edits.
- Minimal fix: resolve with lock inside a transaction and compare
  status/`updated_at` against form state, or add an optimistic-lock check
  with a "changed, refresh" notification (as the table already does for
  status).
- Regression test: two sessions edit the same program; the second stale save
  is rejected and the first change survives.

### FR-06 LOW — program status/Edit paths surface guard failures as errors, unlike invite/reward actions
- Where: `.../Tables/ReferralProgramsTable.php:85` (no try/catch) vs
  `.../Tables/ReferralRewardActions.php:31` (catches Throwable).
- Failure: after an owner switch or on a visible global row, the status
  action throws `AuthorizationException` (fail-closed, but a 500-style error
  instead of the notification pattern used elsewhere).
- Minimal fix: wrap status action in the same try/catch + Notification.
- Regression test: invoking status action after owner change shows an error
  notification and leaves the program unchanged.

### FR-07 LOW — relation managers omit the repo-standard owner re-verification trait
- Where: `.../ReferralProgramResource/RelationManagers/InvitesRelationManager.php:13`,
  `.../ReferralInviteResource/RelationManagers/RewardsRelationManager.php:13`.
- Failure: sibling adapters `use VerifiesRelationManagerOwnerContext`; these
  do not. Data stays safe (queries re-filter via `ReferralFilamentScope`,
  actions re-resolve via guard), but after a tenant switch the manager
  renders an empty table instead of failing closed with 403. For the rewards
  manager the trait is a no-op anyway (`ReferralInvite` is not `HasOwner`).
- Minimal fix: `use VerifiesRelationManagerOwnerContext` in
  `InvitesRelationManager`.
- Regression test: switch owner, then interact with the invites relation
  manager → 403.

### FR-08 LOW — program view hides promised reward values
- Where: `.../Schemas/ReferralProgramInfolist.php:40`.
- Failure: `rewardSpec()` renders only the driver label; amount/points/
  currency/voucher are invisible on the read-only view, forcing operators to
  open the mutable Edit page to audit the promise.
- Minimal fix: format the full spec (minor units via `MoneyFormatter`,
  points, voucher type) in the infolist.
- Regression test: program view shows the configured amount/points.

## Verified contracts (falsified hypotheses)

- Create delegates correctly: `.../Pages/CreateReferralProgram.php:33` runs
  core `CreateReferralProgram`; specs pass `ReferralRewardSpecValidator`
  (`.../Support/ReferralProgramFormData.php:73`). Integer/min rules for
  expiry, caps, amount, and points match core in both form and `toDomain`.
- Table status actions are NOT arbitrary: visible-gated, re-resolved via
  guard, and re-checked against the allowed-from set before save
  (`.../Tables/ReferralProgramsTable.php:84`). Arbitrariness exists only via
  Edit (FR-03). Core has no update/transition Action, so "delegate" requires
  adding one to core (FR-02).
- Invite/reward queries, program filters, widget counts, and relation data
  are owner-safe: `ReferralFilamentScope` constrains `program_id` to the
  owner-scoped program subquery (`.../Support/ReferralFilamentScope.php:17`),
  `OwnerScope` fails closed without owner/explicit-global
  (`packages/commerce-support/src/Support/OwnerScope.php:21`), filter options
  use the scoped program query, and Filament route binding resolves through
  each resource `getEloquentQuery()`, so foreign View/Edit IDs 404.
- All mutation handlers re-resolve server-side through `ReferralProgramGuard`
  and core Actions (revoke `.../ReferralInvitesTable.php:79`; fulfil/reverse
  `.../ReferralRewardActions.php:31,71`); no bulk, export, or delete surfaces
  exist; navigation is config-driven (`getNavigationGroup()`), no static
  `$navigationGroup`.
- Neither `token_hash` nor decrypted `invitee_contact_encrypted` appears
  anywhere in the adapter; only a 12-character `invitee_hash` prefix is shown
  by design (`.../ReferralInvitesTable.php:38`), covered by
  `tests/src/FilamentReferrals/FilamentReferralsTest.php:247`. Residual: the
  unsalted-hash prefix lets a same-tenant operator confirm a guessed contact;
  accepted per docs, not a token/contact leak.
- Money is integer minor units plus currency end-to-end
  (`.../Support/ReferralAdminPresenter.php:97`); no upper bound on amounts is
  intentional operator configuration of their own program, not a bypass.
- Expiry presentation matches core semantics: core pre-sets
  `expired_at = now + expiryDays` at creation
  (`packages/referrals/src/Actions/CreateReferralInvite.php:140`), i.e. a
  deadline, and expiry only transitions Pending/Accepted. Filament's
  "Expiry" label (`.../ReferralInvitesTable.php:48`) is therefore correct;
  the wart is core's `expired_at` naming (deadline vs `*_at` event times like
  `revoked_at`), not an adapter mismatch. Program `starts_at`/`ends_at` and
  reward `fulfilled_at`/`reversed_at` render as stored.

## Coverage gaps in maintained tests

No tests exercise: the Edit page, program status actions (allowed/disallowed/
stale), foreign View/Edit 404s, foreign action submissions, relation managers,
`include_global` behavior, or manual fulfilment without pre-seeded metadata.
Existing 8 tests cover registration, create, list isolation, fulfil/reverse
(pre-seeded), revoke, widget counts, and token/contact non-rendering.
