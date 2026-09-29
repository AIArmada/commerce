# Relation-Manager Owner-Context Restoration — Follow-up (2026-09-28)

Status: RESOLVED 2026-09-29 via the preferred contained fix below.
Originally OPEN: found during the external audit loop for
`livewire-owner-context-assumption-2026-09-28.md` (loop 1, finding 2).
Tracked here, not fixed there: the fix surface (~95 relation managers with
distinct fail-closed semantics) warranted its own project, not piggybacking
on the record-widget rollout.

## Resolution (2026-09-29)

Implemented the preferred contained fix uniformly across all 95
`*RelationManager.php` files (no per-class triage needed — the guard is a
no-op for non-owner-scoped/disabled models, so uniform rollout strictly
covers classes (a), (b), and (c)):

- New `AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext`
  (`packages/commerce-support/src/Filament/Concerns/`): abort-style companion
  to `VerifiesRecordOwnerContext`. Same stamp-and-reverify logic (single
  implementation, shared via a `failOwnerGuard()` seam — default clears,
  this trait aborts 403), guarding the `ownerRecord` prop. Automatic
  `mount{Trait}`/`hydrate{Trait}` wiring, including through nested-trait use
  (verified against Livewire 4.x source and a real request-cycle test).
- Mount hook aborts the whole page when the owner record is already
  cross-owner; hydrate hook runs before action handlers, blocking stale
  child tables and untrusted writes after a mid-session owner change,
  reassignment, or deletion.
- Composed failure mode: Filament's own `hydrateCanAuthorizeAccess` hook
  runs first and touches the owner record for managers whose
  `canViewForRecord()` resolves it (base implementation), so a row deleted
  after mount surfaces as 404 there; overrides that never touch the record
  (e.g. `PricesRelationManager::canViewForRecord()`, class-availability
  only) reach this guard instead and abort 403. Existing-but-cross-owner
  rows always abort 403. All paths fail closed.
- 11 new tests in
  `tests/src/CommerceSupport/VerifiesRelationManagerOwnerContextTest.php`
  (direct-hook + full `Livewire::test` cycles incl. owner switch,
  cross-owner mount, and mid-cycle delete). Existing per-package suites
  re-run green (hooks only fire in a real Livewire lifecycle, so
  direct-instantiation tests are unaffected).
- Canonical docs: `packages/commerce-support/docs/14-multi-tenancy.md`
  ("Livewire Relation Managers"). No per-package doc edits: the guard is a
  transparent backstop (no config, no API), documented once at its owner.

## Accepted residual risk (loop 3, 2026-09-29)

In-request TOCTOU: a request that passes the fresh visibility check could
still render children if the parent is deleted mid-request, after the
check. Declined fix (post-render recheck before serialization):

- No cross-owner impact: owner context is request-scoped and cannot change
  mid-request, so the only "leak" is same-owner data the viewer was
  authorized to see moments earlier.
- Unfixable in principle: a delete can always land after any recheck
  (conceded in the finding itself) — a second check would narrow a
  millisecond window without closing it, at the cost of a query per
  request and a new late-abort failure mode.
- Consistent with the repo posture: every check-then-act authorization
  (`OwnerWriteGuard`, policies, the record-widget guard) shares this
  shape; no serializable-isolation posture exists anywhere.

Exploitation requires millisecond-timed deletion by a party that already
shares the victim's owner scope, yielding only already-authorized data.
Revisit only if a concrete cross-owner variant is demonstrated.

External audit loop 4 (2026-09-29, Codex `gpt-6-luna` max effort)
concurred with this decline and reported NO new findings; loop
terminates. Loop history: loop 1, 2 Minor (deleted-row 403-vs-404
wording + no-touch-manager test added; preview request-cycle coverage
added); loop 2, 1 Minor (malformed-UUID 404 guard added); loop 3,
1 Minor (this TOCTOU, declined); loop 4, NO FINDINGS.

## The mechanism (verified)

Filament's `RelationManager` holds its parent as a locked public model prop
(`#[Locked] public Model $ownerRecord`), restored on subsequent requests via
Livewire's unscoped `newQueryForRestoration()` — the same bypass as record
widgets. Its per-request authorization,

```php
// vendor/filament/.../RelationManagers/Concerns/CanAuthorizeAccess.php
abort_unless(static::canViewForRecord($this->ownerRecord, ...), 403);
```

delegates to `canViewForRecord()`, which for managers WITHOUT a related
resource checks `viewAny` on the RELATED model — never the parent's current
owner scope (`RelationManager.php:287`). The table then queries the
relationship off the restored parent
(`InteractsWithRelationshipTable::makeTable`).

When the related (child) model carries its own `OwnerScope`, the relationship
query re-filters to the current context and the exposure collapses. When the
child model has NO owner scope, a mid-session owner change without reload
followed by a child-component refresh (table search, pagination, `$refresh`)
can render the mount-authorized parent's children under the new context.

## Verified instances

- `filament-vouchers/.../VoucherResource/RelationManagers/VoucherUsagesRelationManager.php`
  (relationship `usages`, no related resource) + `vouchers/.../Models/VoucherUsage.php`
  (no `HasOwner`). Table exposes user/redemption details.
- `filament-cart/.../CartResource/RelationManagers/ConditionsRelationManager.php`
  (relationship `cartConditions`) + `cart/.../Snapshots/CartSnapshotCondition.php`
  (no `HasOwner`).
- `filament-cart/.../CartResource/RelationManagers/ItemsRelationManager.php`
  (relationship `cartItems`, related resource `CartItemResource` — in the
  corrected class (a)) + `cart/.../Snapshots/CartSnapshotItem.php` (no
  `HasOwner`). The table polls every 30s, giving the refresh an organic
  vector. Its row action revalidates the cart via `OwnerWriteGuard` and the
  resource disables edit/delete; no write path found — stale display only.

Repo scale: ~95 `*RelationManager.php` files across `packages/filament-*/src`.
Each needs triage: (a) related-resource managers call that resource's
`canAccess()`, which delegates to `canViewAny()` — NOT a check against the
current parent record, so this class is NOT closed by authorization alone
(correction from loop-2 review; originally mis-triaged as likely closed);
(b) relationship-only managers over owner-scoped children (likely closed
via child scope); (c) managers over unscoped children (exposed — the
instances above plus the one below are in this class; the full class-(c)
list is untriaged).

## Why not fixed with the widget rollout

- Distinct fail-closed semantics: `ownerRecord` is NON-nullable, so the
  widget guard's clear-the-record behavior cannot apply; managers need
  abort/re-resolve behavior instead — a second shared mechanism, not a reuse.
- Distinct blast radius: ~95 files plus a per-manager triage and tests.
- Same trigger condition as the original accepted risk (no-reload owner
  switch, still out of the repo threat model), same narrowed harm class
  (stale display of mount-authorized data, no write path identified).

## What a real fix looks like

Either (preferred, contained):

- One shared mechanism (e.g. a relation-manager concern that revalidates
  `ownerRecord` against the current owner scope on each child request and
  aborts 403 on mismatch, before table queries run), rolled out uniformly
  with tests proving fail-closed refresh.

Or (structural, larger):

- Give the class-(c) child models owner awareness (inherited from the parent),
  which may require schema changes, backfills, and package-wide updates.

Do not fix one manager alone: per-surface patches recreate the inconsistency
 this repo's multitenancy posture forbids.

## Pointers

- Framework: `RelationManager::$ownerRecord`,
  `RelationManagers/Concerns/CanAuthorizeAccess.php`,
  `RelationManager::canViewForRecord()`, `InteractsWithRelationshipTable`.
- Origin: external audit loop 1 on the widget-guard diff (`/tmp/codex-audit1.txt`).
