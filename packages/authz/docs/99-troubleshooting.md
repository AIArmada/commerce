---
title: Authz Troubleshooting
---

## Roles Use the Wrong Key Type

Ensure `permission.models.role` and `permission.models.permission` have not been overridden with Spatie's integer-key models. Authz registers the UUID-backed Commerce Support models by default.

## Scope Is Always Null

Enable both Spatie teams and Authz scopes:

```php
config()->set('permission.teams', true);
config()->set('authz.scopes.enabled', true);
```

## Permission Keys Differ

Check `authz.permissions.case` and `authz.permissions.separator`.
