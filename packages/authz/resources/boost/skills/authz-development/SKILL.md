---
name: authz-development
description: Integrate and develop scoped role and permission administration, authorization checks, and secure impersonation with aiarmada/authz.
---

# Authz development

## Boundaries

- Authorization: `Features/Authorization` owns authz scopes, role and permission definitions, and role/permission pivots.
- Impersonation: `Features/Impersonation` owns session impersonation workflows.
- Use the feature API interfaces from consumers. Eloquent model bindings are reserved for Spatie and framework integration, not cross-package business APIs.
- Keep writes in the owning Actions named in `feature.json`. Never bypass the owning feature through query-builder or relationship writes in consumers.
- Resolve scoped access via `AuthorizationApi::userCanInScope()` or `withScope()`. Null means explicit global; invalid non-null scopes fail closed.

## Role administration

```php
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\CommerceSupport\Data\RecordReference;

$roles = app(RoleAdministrationApi::class);
$role = $roles->ensureRole('manager', 'web', $scopeId, ['orders.view']);
$roles->assignExistingRole(new RecordReference($user->getMorphClass(), (string) $user->getKey()), $role->name, 'web', $scopeId);
```

Role administration is a trusted application-service boundary. Apply the caller's policy and tenant restrictions before invoking it. Reconciliation uses `syncUserRoles()` with the guard, current-team restriction and role scope mode (`all`, `global_only`, `scoped_only`); `syncUserPermissions()` reconciles direct permissions for the active team and guard. Reject invalid selections instead of silently dropping them.

## Impersonation

Use `Features\Impersonation\Api\ImpersonationApi`. The actor must be the current authenticated user, have permission to impersonate and pass target and tenant checks. Supply a safe return URL. Set the relevant guard's driver to `authz-session` in the host application if it needs impersonation-aware session behavior.

## Verification

From the commerce monorepo root:

```sh
php packages/authz/tools/package-check.php --root=packages/authz
./vendor/bin/phpstan analyse -c packages/authz/tools/package-phpstan.neon --autoload-file=packages/authz/tools/Architecture/PackageOwnedWriteRule.php --no-progress
./vendor/bin/pint --test packages/authz packages/filament-authz
./vendor/bin/pest tests/src/Authz tests/src/FilamentAuthz tests/src/FilamentAuthzScoped
```

Check global, tenant, invalid scope, cache freshness, rollback and impersonation denial paths. Do not introduce compatibility aliases or weaken the architecture rules.
