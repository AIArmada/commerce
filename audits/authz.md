# authz Audit — DONE (2026-09-08)

## Verdict

The authorization wrapper (`authz` + `filament-authz`) has passed full
review and implementation. The over-broad scope strip is narrowed, the
discovery binding is request-scoped with Octane flushing, the facade is
renamed without stragglers, generated policies are owner-aware by
default, tenant checks are consistently fail-closed with boot-time
config validation, and core coverage is substantive — with zero rated
findings remaining.

## What was done

- **Narrow scope opt-out** (documented normal parent query in
  `PermissionResource`) — see `code-fixes-record.md`.
- **Per-request `Authz` binding** with Octane state flushing; facade
  accessor unchanged — see `code-fixes-record.md`.
- **Facade renamed** `Authz` -> `FilamentAuthz`; zero old imports
  outside audit metadata — see `code-fixes-record.md`.
- **Owner-aware policy stubs** (`isRecordInCurrentOwnerScope` before
  `$user->can()`) — see `code-fixes-record.md`.
- **Fail-closed tenant checks** (`enforce && teams`) plus boot-time
  separator/teams validation — see `code-fixes-record.md`.
- **Substantive core behavior tests** (`AuthorizationBehaviorTest`) —
  see `code-fixes-record.md`.
- Suites: Authz 13 passed (34 assertions), FilamentAuthz 140 passed
  (251 assertions), FilamentAuthzScoped 12 passed (32 assertions);
  PHPStan level 6 clean on both source packages. No migration required.

## Residual notes

- `authz.scopes` migration toward `HasOwner` for the 57 bespoke-scoped
  `events` models belongs to the `events` track; this package's bridge
  (`ScopesAuthzTenancy`, `ImpersonationScopeGuard`) is unchanged.
- Octane flush path unexercised under real Octane (not installed in
  test env) — exercise on first Octane deploy.
- Existing `down()` methods in the two authz migrations intentionally
  kept (harmless; guideline requires no `down()`, it does not forbid
  it).

If any residual grows teeth, re-open it as a finding. Full finding history
lives in `migration-record.md`, `code-fixes-record.md`, and git history.
