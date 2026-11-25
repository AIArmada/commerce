@if (\Composer\InstalledVersions::getRootPackage()['name'] !== 'aiarmada/commerce')
## Authz

The package uses the modular Laravel Dune layout. Authorization owns scopes, roles, permissions and assignment tables. Impersonation owns the impersonation session workflow.

- Inject `AIArmada\Authz\Features\Authorization\Api\AuthorizationApi` for permission keys and scoped authorization checks.
- Inject `AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi` for role definitions and assignment reconciliation. Use `AIArmada\CommerceSupport\Data\RecordReference` to identify persisted users.
- Inject `AIArmada\Authz\Features\Impersonation\Api\ImpersonationApi` for impersonation. The current authenticated actor and target must pass the impersonation guard.
- The host must authorize administrative actions before invoking the role administration API. Do not expose it as an unguarded HTTP endpoint.
- Use `withScope()` for scoped work. Null denotes deliberate global scope; a non-null unresolved scope is an error, not global access.
- Never write Authz-owned assignment tables directly from another package or a controller, command, Filament page, or job.
- Configure the `authz-session` guard driver explicitly where impersonation-aware session behavior is required; do not replace Laravel's session driver globally.
- Keep Spatie model bindings and Filament/Eloquent integration at the framework boundary.

@verbatim
<code-snippet name="Check access in a scope" lang="php">
use AIArmada\Authz\Features\Authorization\Api\AuthorizationApi;

$allowed = app(AuthorizationApi::class)->userCanInScope($user, 'orders.view', $scope);
</code-snippet>
@endverbatim
@endif
