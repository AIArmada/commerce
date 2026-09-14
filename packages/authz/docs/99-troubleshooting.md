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

Authz sets `permission.team_resolver` to its scope resolver automatically.
If you overrode `permission.team_resolver` with Spatie's default resolver,
team ids bypass scope resolution and scoped role lookups silently miss —
remove the override or point it at
`AIArmada\Authz\Support\AuthzScopeTeamResolver`.

## Team-Scoped Role Lookups Miss

Same cause as above: confirm `permission.team_resolver` resolves to the
Authz scope resolver and that `authz.scopes.enabled` is true.

## Permission Keys Differ

Check `authz.permissions.case` and `authz.permissions.separator`.
