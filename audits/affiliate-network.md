# Affiliate Network Audit — DONE (2026-09-09)

## Verdict

The marketplace package (`affiliate-network` +
`filament-affiliate-network`) has passed full review and
implementation. Ownership runs through one parameterized trait,
creatives scope via their offer chain, discovery/execution boundaries
are documented and enforced, redirects narrow to owner context,
catalog precedence is centralized, dashboards cache per owner — with
zero rated findings remaining.

## What was done

- **Trait unification:** `ScopesByBelongsToOwner` (relation path +
  config key parameterized) replaces both hand-rolled traits; old
  traits deleted; class-based references everywhere including the
  redirect service — see `code-fixes-record.md`.
- **Creative scoping:** `offer.site` chain coverage with regression —
  see `code-fixes-record.md`.
- **Boundary:** network = discovery, `affiliates` = execution;
  `LocalProgramReader` verified read-only; enrollment delegates;
  conversion precedence documented with duplicate guards — see
  `code-fixes-record.md`. `orders` listener untouched.
- **Redirect/security:** explicit-global lookup + owner re-entry,
  signed route at 60/min, `random_bytes` codes, 1MB response cap —
  see `code-fixes-record.md`.
- **Catalog:** single `resolveField()`; readers return raw payloads —
  see `code-fixes-record.md`.
- **Perf:** owner-scoped dashboard, 30s `OwnerCache` widgets — see
  `code-fixes-record.md`.
- **Deps:** filament requires core `affiliates`, suggests only the
  adapter; navigation fallbacks removed — see `code-fixes-record.md`.
  Root `composer.lock` carries none of these path packages — no
  staleness.
- **Index composite deferred:** parent owner indexes verified
  present; exact `(owner_type, owner_id, id)` composite unclaimed —
  revisit with measured pain.
- Suites: AffiliateNetwork 214 passed (442 assertions),
  FilamentAffiliateNetwork 53 passed (81 assertions); PHPStan
  level 6 clean on both packages. No migration required.

## Residual notes

- None open. The `ScopesBy*` naming (without `Owner` infix) is
  historical; renaming would churn filament references for no
  behavior gain.

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
