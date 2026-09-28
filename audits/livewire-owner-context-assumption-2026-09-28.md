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
