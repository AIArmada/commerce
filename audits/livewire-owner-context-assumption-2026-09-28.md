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
