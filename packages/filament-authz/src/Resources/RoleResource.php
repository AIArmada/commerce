<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Concerns\ScopesAuthzTenancy;
use AIArmada\Authz\Features\Authorization\Models\Role;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Resources\RoleResource\Concerns\HasAuthzFormComponents;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages;
use AIArmada\FilamentAuthz\Resources\RoleResource\Schemas\RoleForm;
use AIArmada\FilamentAuthz\Resources\RoleResource\Tables\RoleTable;
use Closure;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RoleResource extends Resource
{
    use HasAuthzFormComponents;
    use ScopesAuthzTenancy;

    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! config('filament-authz.central_app', false)) {
            $query = static::applyTenantScope($query);
        }

        if (config('authz.scopes.enabled', false)
            && config('filament-authz.central_app', false)
            && method_exists(static::getModel(), 'authzScope')) {
            $query->with('authzScope');
        }

        return static::applyConfiguredScopeLimit($query);
    }

    public static function getModel(): string
    {
        return config('permission.models.role', Role::class);
    }

    public static function getModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-authz::filament-authz.resource.role.plural_label');
    }

    public static function canViewAny(): bool
    {
        return static::checkAbility('role.viewAny');
    }

    public static function canCreate(): bool
    {
        return static::checkAbility('role.create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::checkAbility('role.update')
            && static::mayManageRoleName((string) $record->getAttribute('name'))
            && static::isRecordInScope($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::checkAbility('role.delete')
            && static::mayManageRoleName((string) $record->getAttribute('name'))
            && static::isRecordInScope($record);
    }

    protected static function isRecordInScope(Model $record): bool
    {
        if (static::class !== self::class) {
            return static::getEloquentQuery()->whereKey($record->getKey())->exists();
        }

        if (! $record->exists || $record->getKey() === null || ! is_a($record, static::getModel())) {
            return false;
        }

        if (! config('filament-authz.central_app', false) && static::shouldScopeToTenant()) {
            $teamsKey = (string) app(PermissionRegistrar::class)->teamsKey;
            $active = getPermissionsTeamId();
            $recordScope = $record->getAttribute($teamsKey);

            if ($active === null ? $recordScope !== null : (string) $recordScope !== (string) $active) {
                return false;
            }
        }

        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
            $allowed = static::allowedScopeIds();

            if ($allowed !== null) {
                $scopeValue = $record->getAttribute((string) app(PermissionRegistrar::class)->teamsKey);

                if ($scopeValue !== null && ! in_array((string) $scopeValue, $allowed, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    public static function saveDefinition(array $data, ?Model $record = null): Model
    {
        if ($record === null ? ! static::canCreate() : ! static::canEdit($record)) {
            throw new AuthorizationException;
        }

        if (! static::mayManageRoleName((string) $data['name'])) {
            throw new AuthorizationException('Only a super admin may manage the reserved super-admin role.');
        }

        $permissionNames = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'permissions_') && is_array($value)) {
                foreach ($value as $permission) {
                    if (is_string($permission) && $permission !== '') {
                        $permissionNames[] = $permission;
                    }
                }
            }
        }

        $saved = app(RoleAdministrationApi::class)->saveRoleDefinition(
            $record === null ? null : (string) $record->getKey(),
            (string) $data['name'],
            (string) $data['guard_name'],
            static::scopeForWrite($data, $record),
            array_values(array_unique($permissionNames)),
        );

        return $record === null ? static::getEloquentQuery()->whereKey($saved->id)->firstOrFail() : $record->refresh();
    }

    public static function deleteDefinition(Model $record): bool
    {
        if (! static::canDelete($record)) {
            throw new AuthorizationException;
        }

        return app(RoleAdministrationApi::class)->deleteRoleDefinition((string) $record->getKey());
    }

    /** @param array<string, mixed> $data */
    protected static function scopeForWrite(array $data, ?Model $record): string | int | null
    {
        $registrar = app(PermissionRegistrar::class);
        if (! $registrar->teams) {
            return null;
        }

        $key = $registrar->teamsKey;
        if (! config('filament-authz.central_app', false) && static::shouldScopeToTenant()) {
            $active = $registrar->getPermissionsTeamId();
            if (array_key_exists($key, $data) && (string) $data[$key] !== (string) $active) {
                throw new AuthorizationException('The role scope must match the active tenant.');
            }

            return $active;
        }

        $scope = array_key_exists($key, $data)
            ? $data[$key]
            : ($record === null
                ? (config('filament-authz.scoped_to_tenant', true) ? $registrar->getPermissionsTeamId() : null)
                : $record->getAttribute($key));

        if ($scope === '') {
            $scope = null;
        }

        if ($scope !== null && ! is_string($scope) && ! is_int($scope)) {
            throw new AuthorizationException('Invalid role scope.');
        }

        if (config('authz.scopes.enabled', false) && config('filament-authz.central_app', false)) {
            $configured = static::allowedScopeIds();
            if ($scope !== null && $configured !== null && ! in_array((string) $scope, $configured, true)) {
                throw new AuthorizationException('The role scope is outside this panel.');
            }
        }

        return $scope;
    }

    public static function mayManageRoleName(string $name): bool
    {
        $reserved = (string) config('authz.super_admin_role', '');

        return $reserved === '' || mb_trim($name) !== $reserved || static::mayManageSuperAdmin();
    }

    public static function mayManageSuperAdmin(): bool
    {
        $reserved = (string) config('authz.super_admin_role', '');

        return $reserved === '' || UserRoleChecker::hasGlobalRole(Auth::user(), $reserved);
    }

    /** @return list<string>|null */
    public static function allowedScopeIds(): ?array
    {
        if (! config('authz.scopes.enabled', false) || ! config('filament-authz.central_app', false)) {
            return null;
        }

        $configured = static::getConfiguredScopeOptions();

        return $configured === null ? null : array_values(array_filter(
            array_map('strval', array_keys($configured)),
            static fn (string $id): bool => Str::isUuid($id),
        ));
    }

    protected static function checkAbility(string $ability): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        $superAdminRole = (string) config('authz.super_admin_role', '');

        if ($superAdminRole !== '' && UserRoleChecker::hasGlobalRole($user, $superAdminRole)) {
            return true;
        }

        return $user->can($ability);
    }

    public static function form(Schema $form): Schema
    {
        return RoleForm::configure($form, static::getModel(), static fn () => static::getAuthzFormComponents());
    }

    public static function table(Table $table): Table
    {
        return RoleTable::configure($table, static::getModel(), static::deleteDefinition(...));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        $icon = config('filament-authz.navigation.icons.roles');

        return is_string($icon) ? $icon : null;
    }

    public static function getActiveNavigationIcon(): ?string
    {
        $icon = config('filament-authz.navigation.icons.roles_active');

        return is_string($icon) ? $icon : null;
    }

    public static function getNavigationLabel(): string
    {
        $label = config('filament-authz.navigation.label');

        return is_string($label) ? $label : __('filament-authz::filament-authz.navigation.roles');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.navigation.sort');
    }

    public static function getNavigationBadge(): ?string
    {
        $badge = config('filament-authz.navigation.badge');

        return is_string($badge) ? $badge : null;
    }

    /**
     * @return string | array<string> | null
     */
    public static function getNavigationBadgeColor(): string | array | null
    {
        $color = config('filament-authz.navigation.badge_color');

        return is_string($color) || is_array($color) ? $color : null;
    }

    public static function getNavigationParentItem(): ?string
    {
        $parent = config('filament-authz.navigation.parent_item');

        return is_string($parent) ? $parent : null;
    }

    public static function getCluster(): ?string
    {
        $cluster = config('filament-authz.navigation.cluster');

        return is_string($cluster) ? $cluster : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-authz.navigation.register', true) && static::canViewAny();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('filament-authz.role_resource.slug', 'authz/roles');
    }

    protected static function applyConfiguredScopeLimit(Builder $query): Builder
    {
        if (! config('authz.scopes.enabled', false) || ! config('filament-authz.central_app', false)) {
            return $query;
        }

        $validUuids = static::allowedScopeIds();

        if ($validUuids === null) {
            return $query;
        }

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        if ($validUuids === []) {
            return $query->whereNull($teamsKey);
        }

        return $query->where(function (Builder $query) use ($teamsKey, $validUuids): void {
            $query->whereNull($teamsKey);
            $query->orWhereIn($teamsKey, $validUuids);
        });
    }

    /**
     * @return array<string, string> | null
     */
    protected static function getConfiguredScopeOptions(): ?array
    {
        $override = FilamentAuthzPlugin::resolveForPanel()?->getRoleScopeOptions();

        if ($override !== null) {
            return $override;
        }

        $configured = config('filament-authz.role_resource.scope_options');

        if ($configured instanceof Closure) {
            /** @var mixed $configured */
            $configured = app()->call($configured);
        }

        if (! is_array($configured)) {
            return null;
        }

        return collect($configured)
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }
}
