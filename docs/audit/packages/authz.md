# Audit Report: `authz`

**Package:** `aiarmada/authz`  
**Surface:** core  
**Family:** foundation  
**Audited:** 2026-06-27 — Commit `7d1dc95fa`  

---

## Purpose

Framework-agnostic Spatie Permission integration providing UUID permission schema, wildcard permissions, scope resolution, and impersonation services.

## Architecture — PASS

Relatively thin package that wraps `spatie/laravel-permission` with custom models stored in `commerce-support`. Main components:
- **Services (3):** `ImpersonateManager`, `PermissionKeyBuilder`, `WildcardPermissionResolver`
- **Support (6):** `AuthzScopeContext`, `AuthzScopeResolver`, `AuthzScopeTeamResolver`, `UserRoleChecker`, `CommandProhibitor`, `ImpersonationScopeGuard`
- **Concerns (3):** `HasAuthzScope`, `ScopesAuthzTenancy`, `SyncsRolePermissions`
- **Events (2):** `TakeImpersonation`, `LeaveImpersonation`
- **Guard (1):** Custom `SessionGuard` with `quietLogin`/`quietLogout`
- **Console (2):** `SuperAdminCommand`, `SyncAuthzCommand`
- **Migrations (2):** authz_scopes table + permission tables

Provider registers Spatie model overrides (`CommerceSupport\Models\Permission`, `Role`), team resolver, wildcard Gate hooks, super-admin Gate hook, Blade directives for impersonation, session auth driver extension, and Octane cache-flush listeners.

## Implementation Quality — PASS

- All UUID primary keys in migrations
- Octane-safe: `RequestReceived` listener clears permission cache per request
- `ImpersonateManager` uses `quietLogin`/`quietLogout` for CSRF-safe impersonation
- `provide` in Spatie team context restored in `finally` blocks (in `Authz::withScope`, `Authz::withoutTeams`, `Gate::before`)
- `ImpersonationScopeGuard` scopes user queries to the current team/scope for target validation
- `sanitizeBackToUrl` prevents open redirects in impersonation back-url
- `Prohibitable` trait allows commands to be disabled per environment
- `SyncAuthzCommand` validates guards against `auth.guards` config

## Security — PASS

- Open redirect protection on impersonation back-URL
- `ImpersonationScopeGuard::canAccessTarget` prevents cross-scope impersonation
- Super-admin Gate hook uses `method_exists($user, 'hasRole')` guard
- `CommandProhibitor` allows disabling destructive commands via `CommandProhibitor::prohibitDestructiveCommands()`

## Tests — PASS

**10+ test files found** under `tests/src/FilamentAuthz/Unit/` that directly test authz package classes:
- `AuthzServiceTest` — `PermissionKeyBuilder`
- `ImpersonateControllerTest` — `ImpersonateManager` (take/leave/clear/sanitize)
- `WildcardPermissionResolverTest` — wildcard matching logic
- `ImpersonationScopeGuardTest` — cross-scope impersonation guard
- `AuthzScopeContextTest` — scope context lifecycle
- `CommandsTest` — `Prohibitable`, `SuperAdminCommand`, `SyncAuthzCommand`
- `TraitsTest` — `SyncsRolePermissions`
- `AuthzHelpersTest` — helper functions
- `GateHooksTest` — super-admin/wildcard Gate hooks
- `ConfigTest`

Tests are in `filament-authz`'s test directory rather than a standalone `authz` test directory, but they exercise the authz code directly.

## Documentation — PASS

5 doc files covering overview, installation, configuration, usage, and troubleshooting. Concise and clear.

## Issues Found

**Minor:**
2. `Authz::clearCache()` has an empty body (line 88-89). The `flushPermissionCache()` method handles cache clearing but `clearCache()` is a public API that does nothing.
3. `SuperAdminCommand::getEmailColumn()` always returns `'email'` regardless of what the guard's user model uses.
4. `ImpersonateManager::updatePasswordHashInSession()` (line 358) uses `auth()->guard()` (default guard) instead of the specific guard being updated, which could be wrong if the default guard differs.
5. Empty directory `src/Models/Concerns/`, `src/Exceptions/`, `src/Traits/`, `src/Jobs/` — dead scaffolding.

## Final Status

**Ready with minor improvements.** Solid code quality, architecture, and tests (under filament-authz test directory).

## Summary

| Category | Result |
|----------|--------|
| Purpose | Clear |
| Architecture | Clean wrapper on Spatie Permission |
| Implementation | Solid — minor issues noted |
| Security | Strong (redirect protection, scope guards) |
| Tests | 10+ files (under filament-authz test dir) |
| Docs | 5 files, concise |
