---
title: Authz Configuration
---

## Main Settings

The shipped `config/authz.php` top-level keys, with their default values:

```php
'database' => [
    'table_prefix' => '',
    'tables' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
        'scopes' => 'authz_scopes',
    ],
],
'super_admin_role' => 'super_admin',
'guards' => ['web'],
'users' => [
    'email_column' => 'email',
    'name_column' => 'name',
],
'wildcard_permissions' => true,
'permissions' => [
    'separator' => '.',
    'case' => 'camel',
],
'custom_permissions' => [],
'sync' => [
    'permissions' => [],
    'roles' => [],
],
'scopes' => [
    'enabled' => false,
    'auto_create' => true,
    'enforce' => true,
],
'impersonate' => [
    'guard' => 'web',
],
```

The `authz.guards` list is the shared default consumed by core commands and the Filament adapter. Every listed guard must exist in `config/auth.php`.

`authz.custom_permissions` seeds extra permission definitions at boot, and
`authz.sync` (`permissions`, `roles`) is the declaration set reconciled by
`php artisan authz:sync`. Both ship empty.

The `authz.permissions.separator` value must be exactly one non-alphanumeric
character. The service provider rejects invalid values during application boot.
The `authz.permissions.case` value must be one of `snake`, `kebab`, `camel`,
`pascal`, `upper_snake`, or `lower`; unknown values throw during boot.
When `authz.scopes.enabled` is true, `permission.teams` must also be enabled;
the provider rejects that incompatible configuration during boot.

## Team Resolver

`permission.team_resolver` decides how Spatie reads and writes the active team
id, and Authz dispatches scope switching to the resolver the registrar actually
uses, in three modes:

- `AuthzScopeTeamResolver` — when `authz.scopes.enabled` is true, Authz sets
  `permission.team_resolver` to its scope resolver so Spatie routes team ids
  through `AuthzScope` records. An unset resolver — or one still pointing at
  Spatie's default resolver — is replaced automatically; only an explicit
  custom `permission.team_resolver` is left untouched.
- `OwnerContextTeamResolver` — when `permission.teams` is enabled and
  `commerce-support` is installed, scope switching runs through
  `OwnerContext`.
- Spatie's `DefaultTeamResolver` or a custom resolver — scope switching is
  performed through the Spatie team API (`setPermissionsTeamId()`), and the
  previous team id is restored in `finally`, including when a callback throws.
  If the active resolver cannot honor the requested team — for example an
  immutable resolver that ignores the setter — the switch fails with
  `ScopeResolutionException` before the callback runs; the callback never
  executes under the previous team. A resolver that changes state and then
  fails — including a getter that throws during the post-switch verification —
  is rolled back to the previous team id before the failure surfaces. If the
  rollback cannot be honored either, the switch fails with
  `ScopeRestorationException` instead of continuing under an unknown scope.

A custom resolver that bypasses `AuthzScope` records will make team-scoped
role lookups silently miss when `authz.scopes.enabled` is true.

## Scope Resolution Semantics

`Authz::withScope()` and `Authz::userCanInScope()` resolve the requested scope
against the active team-resolver namespace:

- `AuthzScopeTeamResolver` — scope references resolve to `AuthzScope` ids. Pass
  an `AuthzScope`, a model that owns one, or a scope id. Model references are
  created on demand when `authz.scopes.auto_create` is true.
- `OwnerContextTeamResolver` — scope references resolve to owner keys. Pass the
  owner model or a raw owner key (resolved through
  `commerce-support.owner.team_type`).
- `DefaultTeamResolver` or a custom resolver — scope references resolve to
  Spatie team ids: persisted models resolve to their key and raw ids pass
  through unchanged. `AuthzScope` records are not consulted in this mode.

Explicit `null` always selects the deliberate global scope, and
`Authz::userHasPermissionAcrossScopes()` remains the cross-scope surface.

> [!WARNING]
> A non-null scope that cannot resolve — an invalid type, an unsaved model, a
> deleted `AuthzScope`, a model without a scope while `auto_create` is
> disabled, a raw owner key without a configured `team_type`, or a resolver
> that cannot honor the team switch — throws
> `AIArmada\Authz\Exceptions\ScopeResolutionException`. It never falls back to
> global permissions. `userCanInScope()` catches that classified setup failure
> and returns `false`; `withScope()` lets it surface to the caller.
> When the resolver changed state before failing, Authz first rolls back to the
> previous team id; a rollback the resolver cannot honor throws
> `AIArmada\Authz\Exceptions\ScopeRestorationException`, which is never
> converted to `false`.

Exceptions raised later by an ability or policy callback are not scope
failures: they propagate unchanged through both methods in every resolver
mode, even when the exception class is `ScopeResolutionException`.

Scope context is restored on every exit path: the previous team id, owner
context, and scope override are reinstated before an exception surfaces,
including when a callback throws or a switch setup fails after changing state.

## Session Guard

Authz extends the `session` auth driver for every guard so impersonation can
switch identities quietly. Host applications with a custom `session` driver
must register their driver after the Authz service provider, or the Authz
guard will replace it.

## Leave-Impersonation Route

Authz registers `POST authz/leave-impersonation` (route name
`authz.leave-impersonation`) behind the `web` and `auth` middleware. It ends
the impersonation session and redirects to the sanitized back-to URL.
