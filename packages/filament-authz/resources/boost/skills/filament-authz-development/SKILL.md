---
name: filament-authz-development
description: Build and extend the Filament v5 roles, permissions, users, discovery, and impersonation admin UI with aiarmada/filament-authz.
---

# Filament Authz development

## Boundaries

- Adapter only: this package owns the Filament UI. Role/permission math, scopes, and assignment tables live in `aiarmada/authz`.
- Inject the core feature API (`RoleAdministrationApi`, `ImpersonationApi`) instead of writing authz-owned tables from a resource, page, command, or relation manager.
- Filament tenancy is presentation only. Revalidate every inbound id against the active owner/team scope before attach or update.
- Keep navigation config-driven (`getNavigationGroup()`, `getNavigationSort()`) so the `CommerceNavigation` runtime override engine keeps working.

## Services

```php
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\CommerceSupport\Data\RecordReference;

// Persist permissions for a guard.
app(RoleAdministrationApi::class)->ensurePermissions('web', $names);

// Reconcile a user's roles (guard, restrict-to-team, scope mode, allowed scopes, super admin).
app(RoleAdministrationApi::class)->syncUserRoles(
    new RecordReference($user->getMorphClass(), (string) $user->getKey()),
    $roleIds,
    'web',
    true,
    'all',
    null,
    false,
);
```

Use `SyncAuthzTenant` in `tenantMiddleware` to keep the Spatie team context aligned with the active tenant, and `ImpersonationApi` for the impersonation flow.

## Verification

From the commerce monorepo root:

```sh
php packages/filament-authz/tools/package-check.php --root=packages/filament-authz
./vendor/bin/phpstan analyse -c packages/filament-authz/tools/package-phpstan.neon --autoload-file=packages/filament-authz/tools/Architecture/PackageOwnedWriteRule.php --no-progress
./vendor/bin/pint --test packages/filament-authz
./vendor/bin/pest tests/src/FilamentAuthz
```

Never introduce compatibility aliases, inline static-analysis suppressions, or host `App\*` imports.
