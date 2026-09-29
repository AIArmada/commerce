# Livewire Owner-Context Restoration — Recorded Assumption (2026-09-28)

Status: ACCEPTED RISK, not a bug backlog item. Revisit only if a no-reload
store switcher (or equivalent mid-session owner change) becomes a real scenario.

## The mechanism

Livewire rebuilds public model properties from the raw ID via
`newQueryForRestoration()`, which bypasses Eloquent global scopes — including
the `OwnerScope` that enforces tenant isolation. Filament record pages and
record widgets therefore trust their mount-time authorization on subsequent
requests (polling, actions) instead of re-verifying owner context.

Concrete story: an admin opens Store A's Order #123 (authorized, correctly
resolved). If her session's current store silently became Store B with no page
reload, the next widget refresh would restore the order by ID with no owner
filter and keep rendering Store A's data inside a Store B session.

## Why this is accepted, not fixed

1. **Narrow realistic harm.** The viewer was already authorized for the
   record at mount. Worst case is stale-context display after an
   invisible mid-session switch or revocation — not viewing orders the user
   could never open.
2. **The page shares the exposure.** `ViewOrder` (and every Filament record
   page) holds its record the identical way. Hardening one widget while the
   page behaves the same fixes nothing; it would be inconsistency
   masquerading as security.
3. **Repo-wide pattern.** Five `filament-vouchers` record widgets hold
   identical public `?Model $record` props with no re-resolution. Filament
   core itself behaves this way on every record page.
4. **Matches the repo threat model.** The multitenancy contract requires
   request-scoped resolution on the web (fresh loads re-resolve, scoped)
   and explicit restoration in jobs/commands. Owner-switch-without-reload
   is outside that model: store switchers navigate/reload, which re-mounts
   everything through authorized, scoped resolution.

Independently reviewed (3-round audit, second-eyes model concurred): would
not block merge; consistent-with-siblings posture acceptable under the
stated threat model.

## What a real fix would look like (if ever wanted)

Not a widget patch — a systemic feature project:

- One shared mechanism (e.g. stamp owner context into record components at
  mount; verify on each subsequent request; force reload / fail closed on
  mismatch), living in `commerce-support`.
- Design decisions: what counts as "context changed", fail-closed behavior,
  interaction with polling.
- Tests proving fail-closed refresh, rolled out across ALL record pages and
  record widgets uniformly — doing one surface alone recreates the
  inconsistency described above.

## Pointers

- Widget that prompted the review:
  `packages/filament-orders/src/Widgets/OrderTimelineWidget.php`
  (public `?Order $record`, mounted on record-authorized `ViewOrder`;
  `addNote()` re-authorizes per record).
- Sibling pattern: `packages/filament-vouchers/src/Widgets/*` (5 widgets).
- Framework behavior:
  `vendor/livewire/livewire/.../ModelSynth.php` (`newQueryForRestoration`),
  `vendor/.../Eloquent/Model.php` (scope removal).
- Repo contract: `.ai/multitenancy` rules (request-scoped web resolution).

---

# Re-verification (2026-09-28)

Scope: re-verify every mechanism claim against current code; reassess
the verdict. No behavior changes. Verdict: **ACCEPTED RISK STANDS**,
but reasons 2 and 3 below the fold were factually wrong and are
corrected here. The exposure is narrower than originally stated.

## Confirmed as written

- Restoration bypass is real: Livewire `ModelSynth` restores public
  models via `newQueryForRestoration($key)`, which is
  `newQueryWithoutScopes()->whereKey($ids)` — `OwnerScope` included.
  (`vendor/livewire/.../SupportModels/ModelSynth.php:94`,
  `vendor/laravel/.../Eloquent/Model.php:1965-1968`.)
- Five `filament-vouchers` widgets hold identical public `?Model
  $record` props with no re-resolution.
  (`packages/filament-vouchers/src/Widgets/*.php`.)
- Fresh loads resolve scoped: `OrderResource::getEloquentQuery()`
  returns `Order::query()->forOwner(...)`.
- No no-reload switcher exists in-repo: no tenant/store switcher UI,
  no Filament tenancy registration (only a defensive `hasTenancy()`
  check in `filament-authz`), owner context is request-scoped via
  the abstract `OwnerIdentificationMiddleware` (no concrete
  session-based resolver in-repo). The record's trigger condition
  ("no-reload store switcher becomes real") has NOT materialized.
- No polling on `OrderTimelineWidget`: re-render requires interaction.

## Corrected: record pages fail closed per request

Reason 2 ("the page shares the exposure") and the "Filament core
itself behaves this way on every record page" half of reason 3 are
wrong. Livewire invokes `hydrate{Trait}` hooks on every subsequent
request, and Filament's record-page trait hooks record-level
authorization into it:

- `InteractsWithRecord::hydrateCanAuthorizeAccess()` →
  `canAccess(['record' => ...])` → policy `view` →
  owner-aware `canAccessOrder()` against the CURRENT context.
  After an owner switch, the page shell 403s on its next request.
  (`vendor/filament/.../Resources/Pages/Concerns/InteractsWithRecord.php`,
  `vendor/livewire/.../SupportLifecycleHooks.php:46-53`.)
- `ViewOrder` does not override `canAccess`/`canView`, so the
  default applies. (Its `->authorize()` closures are action-level.)

Base widgets hook only widget-level `static::canView()` (no record),
so record widgets remain the exposed surface — they are the outlier,
not the norm. The concrete story above still holds for the widget,
with one amendment: the page shell around it would 403 on its own
next request while the widget kept rendering stale data.

## Strengthened: exposure is display-only

All known widget write actions re-verify against the current context
per request and fail closed after a switch:

- `OrderTimelineWidget::addNote()` → Gate `addNote` → policy →
  `belongsToOwner(OwnerContext::resolve())`.
- `QuickApplyVoucherWidget::applyVoucher()` and
  `VoucherSuggestionsWidget::applySuggestion()` → explicit
  `OwnerQuery::applyToEloquentBuilder(...)->whereKey(...)->exists()`
  visibility check before mutating.

Combined with per-request page authorization, the residual exposure
is stale DISPLAY of records the viewer was authorized for at mount.
No write path and no never-authorized read were found.

## Restated rationale (replaces reasons 2–3 as written)

1. (unchanged) Narrow harm — now narrowed further to stale display;
   writes and page shells fail closed via owner-aware checks.
2. (corrected) Filament record pages re-authorize per request; only
   record widgets (1 orders + 5 vouchers) skip record-level
   re-verification. Per-surface hardening would genuinely close that
   surface — but leaving five siblings untouched recreates the same
   inconsistency, so the uniform-rollout argument for a systemic fix
   stands.
3. (corrected) The repo-wide pattern is real for widgets, not for
   record pages. Filament core record pages fail closed; Filament
   core widgets check widget-level access only.
4. (unchanged, verified) Threat model: no in-repo no-reload owner
   switch exists; owner context is request-scoped. Revisit trigger
   unchanged.

## Adjacent observation (not verified in this pass)

Record pages declare their record `#[Locked]`; record widgets do not.
Whether an unlocked model prop admits key tampering depends on
Livewire's snapshot integrity, which was not examined here. If the
systemic project ever starts, include this in its threat surface.

---

# Resolution (2026-09-28)

Status: FIXED via the systemic project sketched above. The accepted-risk
posture is superseded; the revisit trigger is closed.

## What was built

One shared mechanism in `commerce-support`, rolled out uniformly:

- New `AIArmada\CommerceSupport\Filament\Concerns\VerifiesRecordOwnerContext`
  trait (`packages/commerce-support/src/Filament/Concerns/`). It stamps the
  mount-time owner context (tuple + explicit-global flag) and record identity
  (class, key, owner tuple) into a `#[Locked]` snapshot prop, then re-verifies
  on every subsequent request via Livewire's automatic `mount{Trait}` /
  `hydrate{Trait}` hooks. Any mismatch — changed context, changed record
  identity, missing stamp, or failed fresh scoped visibility check — fails
  closed by clearing the record, so the widget renders its empty state and
  write actions no-op. Mount-time verification also covers lazy widgets
  (all Filament v5 widgets are lazy by default; their mount runs on a later
  request). The guard skips silently for null/unsaved records, non-owner-
  scoped models, and disabled scoping.
- Rolled out to all 6 record widgets (verified by repo-wide sweep: no other
  widget holds a model prop): `OrderTimelineWidget` (filament-orders) plus
  `AppliedVouchersWidget`, `QuickApplyVoucherWidget`,
  `VoucherSuggestionsWidget`, `VoucherUsageTimelineWidget`,
  `VoucherCartStatsWidget` (filament-vouchers). Each change is trait use +
  `#[Locked]` on `$record`. Existing per-method/per-action owner checks were
  kept as defense in depth.
- Record pages needed no change: they already fail closed per request via
  Filament core (`hydrateCanAuthorizeAccess` → policy), confirmed against
  installed Filament v5.8.4 and the Filament 5.x security docs.

## Design decisions (per the open questions)

- "Context changed" = stamped context tuple or explicit-global flag differs
  from current, OR record class/key/tuple differs, OR fresh scoped `exists()`
  fails.
- Fail-closed = clear record + stamp (graceful empty state), not abort/redirect:
  widgets are sub-components, polling/lazy loads stay quiet, and the page
  shell independently 403s on its own next request.
- Polling interaction: each poll re-runs the same check; mismatch renders
  empty, no error storms or redirect loops.

## Adjacent observation: closed

- All 6 widget `$record` props are now `#[Locked]`, matching record pages.
- Livewire 4.x docs confirm model props already carry ID-tamper protection
  ("Model properties are secure by default"); `#[Locked]` adds explicit
  update rejection (`CannotUpdateLockedPropertyException`, verified in
  installed Livewire v4.4.6). The string stamp prop requires its lock (plain
  props are freely mutable by default) and has it.

## Verification

- 62 new tests, all passing: 34 trait contract tests
  (`tests/src/CommerceSupport/VerifiesRecordOwnerContextTest.php`, incl.
  real `Livewire::test` round-trips for mount/hydrate wiring, refresh after
  owner switch, mid-cycle deletion, locked-prop rejection, lazy resume, and
  custom-prop override, plus fixed-owner pin coverage), 5 orders rollout
  tests, 19 vouchers rollout tests (dataset-uniform across all 5 widgets,
  incl. read-path emptiness after a switch for the two widgets whose display
  paths previously had no owner check: `OrderTimelineWidget`,
  `VoucherCartStatsWidget`), 4 event-preview rollout tests.
- No regressions: `tests/src/CommerceSupport`, `tests/src/FilamentOrders`,
  `tests/src/FilamentVouchers`, `tests/src/FilamentEvents` all green except
  1 pre-existing `ConfigurationPagesTest` failure reproduced on the clean
  tree (unrelated).
- PHPStan level 6 clean on all touched packages (combined analysis, which
  also checks the trait in each consumer's context); Pint clean on all
  touched files.
- Livewire hook mechanics verified against installed vendor code (trait-hook
  naming, mount-param auto-fill ordering before `mount{Trait}`, lazy
  mount-on-later-request with snapshot-restored props, locked enforcement)
  and current Livewire 4.x / Filament 5.x docs.

## External audit loop 4 (2026-09-28)

NO FINDINGS. The loop-3 suppression fix was verified closed against
installed Livewire v4.4.6 / Filament v5.8.4, and the full tracked-plus-
untracked sweep confirmed all six record widgets and `EventPublicPreview`
guarded and locked, with no additional owner-scoped Filament public model
props. Loop terminates.

## External audit loop 3 (2026-09-28)

Loop-2 fixes both verified closed; no other new issue in guard, stamp,
Octane state, or rollout. One new minor, fixed:

1. (Minor, FIXED) The visibility check ignored
   `OwnerScopeOverride::suppressIncludeGlobal()`, staying more permissive
   than `OwnerScope` inside batch overrides. It now applies the same
   suppression. Covered by mount + hydrate override tests. No current
   caller runs the guard inside the override (only `OwnerBatchRunner`
   sets it), so current behavior is unchanged.

## External audit loop 2 (2026-09-28)

Loop-1 fixes #1, #3, #4, #5 all verified closed by re-audit; the
relation-manager deferral was judged sound. Two new minors, both fixed:

1. (Minor, FIXED) The guard ignored a configured fixed `OwnerScopeConfig::$owner`.
   Both the stamp and the visibility check now use the effective owner
   (pin wins over ambient resolution, mirroring `OwnerScope`), so pinned
   models are immune to context changes and a pin counts as sufficient
   context. No in-tree model uses a pin, so current behavior is unchanged.
   Covered by 4 new fixture tests.
2. (Minor, FIXED) The custom-prop override had no round-trip coverage. Added
   a real `Livewire::test` cycle for an `event`-prop component (mount stamp,
   refresh-after-switch clear).

The loop also corrected the follow-up triage: related-resource managers call
`canAccess()` → `canViewAny()` (no parent-record check), so that class is
not closed by authorization; and it added a third verified instance
(`ItemsRelationManager` + unscoped `CartSnapshotItem`, 30s poll). Both are
recorded in `audits/relation-manager-owner-context-2026-09-28.md`.

## External audit loop 1 (2026-09-28)

An independent model audit (report-only) raised 5 findings; 4 fixed in this
pass, 1 tracked as a separate project:

1. (Major, FIXED) `filament-events/.../Pages/EventPublicPreview.php` holds
   `public ?Event $event` with mount-only scoping. The "no
   subsequent-request vector" claim was wrong: Livewire magic actions such as
   `$refresh` are callable on every component. Fixed by generalizing the
   trait with an overridable `ownerGuardedPropName()` (default `record`) and
   applying it to the page (guarding `event`), plus `#[Locked]` on both
   `$event` and `$eventId`. Covered by 4 new rollout tests.
2. (Major, TRACKED SEPARATELY) Relation-manager child requests restore the
   locked `ownerRecord` unscoped, and managers without a related resource
   authorize via `viewAny` on the child model — never the parent's owner
   scope. Verified for `VoucherUsagesRelationManager`/`VoucherUsage` and
   `ConditionsRelationManager`/`CartSnapshotCondition` (both child models
   unscoped). ~95 managers; non-nullable parent prop needs abort-style
   fail-closed, a distinct mechanism. Recorded as
   `audits/relation-manager-owner-context-2026-09-28.md` with a fix sketch.
3. (Minor, FIXED) Restored models are PHP 8.4 native lazy proxies: a row
   deleted after mount threw `ModelNotFoundException` on first guard access
   instead of rendering empty. Both hooks now convert that exception to the
   same fail-closed clear. Covered by a real round-trip deletion test.
4. (Minor, FIXED) The visibility check used `newQueryWithoutScopes()`,
   dropping every model scope. It now uses `newQuery()` minus only
   `OwnerScope`, so independent model scopes still apply. Covered by an
   extra-scope fixture test.
5. (Minor, FIXED) Direct-hook tests did not prove Livewire wiring. Added
   real `Livewire::test` round-trips (mount stamp, refresh-after-switch
   clear, fresh-storage restore proof, mid-cycle delete, locked-update
   rejection, lazy resume incl. cross-owner resume), plus a same-owner
   key-swap test.

Latent issue noticed (RESOLVED 2026-09-29): `EventPublicPreview` read
its event from a `?event=` query value, but Livewire page mounts receive
route params only — the page always mounted empty via URL. Fixed with the
Filament-idiomatic route-placeholder shape: slug
`events/public-preview/{eventId}`, `mount(?string $eventId = null)` (a
placeholder named `event` would collide with the `?Event $event` prop
during auto-fill), `ViewEvent` link updated to the `eventId` key, and
uniform 404 for missing/empty/malformed/cross-owner ids (no existence
leak; the UUID check also avoids a PostgreSQL uuid-cast 500 on malformed
ids — loop-2 audit finding). Post-mount staleness stays covered by
`VerifiesRecordOwnerContext`. Covered by mount tests (null/empty/malformed/
unknown/cross-owner 404 + slug placeholder + getUrl path-fill + Livewire
refresh-after-switch cycle).
