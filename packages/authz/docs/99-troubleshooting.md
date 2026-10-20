---
title: Authz Troubleshooting
---

## Roles Use the Wrong Key Type

Ensure `permission.models.role` and `permission.models.permission` have not been overridden with Spatie's integer-key models. Authz registers its UUID-backed models by default.

## Scope Is Always Null

Enable both Spatie teams and Authz scopes:

```php
config()->set('permission.teams', true);
config()->set('authz.scopes.enabled', true);
```

Authz sets `permission.team_resolver` to its scope resolver automatically,
replacing an unset value or Spatie's default resolver. If you set a custom
`permission.team_resolver` that bypasses scope resolution, scoped role lookups
silently miss — remove the override or point it at
`AIArmada\Authz\Support\AuthzScopeTeamResolver`.

## Team-Scoped Role Lookups Miss

Same cause as above: confirm `permission.team_resolver` resolves to the
Authz scope resolver and that `authz.scopes.enabled` is true.

## ScopeResolutionException on Scoped Checks

The requested scope is non-null but cannot resolve in the active team-resolver
namespace, and Authz fails closed instead of falling back to global
permissions:

- Authz scope resolver mode (`authz.scopes.enabled`): the model has no
  `AuthzScope` while `authz.scopes.auto_create` is `false`, the `AuthzScope`
  record was deleted, or the scope value is invalid.
- Owner-context mode: a raw owner key is passed while
  `commerce-support.owner.team_type` is not configured.
- Default or custom resolver mode: the active Spatie team resolver ignores
  `setPermissionsTeamId()` (an immutable resolver), so it cannot honor the
  requested team.

Fix the scope reference or create the missing scope record. Explicit global
work must pass `null` (or use `Authz::userHasPermissionAcrossScopes()`), not an
unresolvable value.

`userCanInScope()` returns `false` only for this setup failure. If an ability
or policy callback throws while the requested scope is active, that exception
propagates to your code instead — including a `ScopeResolutionException` —
after the previous scope state is restored.

## ScopeRestorationException After a Failed Switch

The active team resolver accepted the requested switch and then failed, and
the rollback to the previous team could not be honored either. Authz fails
loud so the request cannot continue under an unknown scope; unlike a resolved
setup rejection, this is never converted to `false`.

Fix the resolver so it honors every requested team synchronously and restores
state correctly — for example a setter that mutates state before throwing, or
a getter that throws while the requested team is active — then retry the
scoped operation.

## Gate Allows a Revoked Wildcard

Wildcard verdicts are read from current scoped assignments on every check, so
a revocation applies to the next Gate check even on user instances loaded
before the change. If a stale verdict still appears, confirm the check runs
through the Gate and that no application-level authorization cache holds the
old result; `Authz::clearCache()` clears Spatie's permission collection and
the caller's loaded relations for the current request.

## Permission Keys Differ

Check `authz.permissions.case` and `authz.permissions.separator`.
