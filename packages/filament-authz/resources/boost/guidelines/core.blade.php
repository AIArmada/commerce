@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Filament Authz

This package is a thin Filament v5 adapter over `aiarmada/authz`. It owns the roles, permissions, and users admin UI, entity discovery, and the impersonation UI. All authorization math lives in `authz`.

- Inject `AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi` for role, permission, and assignment reconciliation. Never write the authz-owned assignment tables directly from a resource, page, or command.
- Resolve users by `AIArmada\CommerceSupport\Data\RecordReference`; pass ids as strings so UUID and integer keys behave identically.
- Treat Filament tenancy as presentation only. Revalidate every submitted id against the active owner/team scope on the server before writing.
- Use `SyncAuthzTenant` in a panel's `tenantMiddleware` to keep the Spatie team context aligned with the active Filament tenant.
- Keep the adapter UI-only. If behavior or a calculation is missing, add it to `authz` and consume it through the feature API.

@verbatim
<code-snippet name="Reconcile a role through the core API" lang="php">
use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\CommerceSupport\Data\RecordReference;

app(RoleAdministrationApi::class)->ensurePermissions($guard, $permissionNames);
app(RoleAdministrationApi::class)->syncUserRoles(
    new RecordReference($user->getMorphClass(), (string) $user->getKey()),
    $roleIds,
    $guard,
    true,
    'all',
    null,
    false,
);
</code-snippet>
@endverbatim
@endif
