<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Support;

use AIArmada\Authz\Features\Authorization\Api\RoleAdministrationApi;
use AIArmada\Authz\Features\Authorization\Models\AuthzScope;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class UserAuthzForm
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        $components = [];

        if (config('filament-authz.user_resource.form.roles', true)) {
            $components[] = static::rolesComponent();
        }

        if (config('filament-authz.user_resource.form.permissions', true)) {
            $components[] = static::permissionsComponent();
        }

        if ($components === []) {
            return [];
        }

        return [
            Section::make('Access Control')
                ->description('Assign roles to manage user permissions. Direct permission assignment is available for special cases.')
                ->schema($components)
                ->columns(2),
        ];
    }

    protected static function rolesComponent(): Select
    {
        return Select::make('roles')
            ->label('Roles')
            ->options(fn (): array => static::getRoleOptions())
            ->afterStateHydrated(function (Select $component, ?Model $record): void {
                static::setRoleStateForRecord($component, $record);
            })
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Select roles to grant permissions to this user.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                static::syncRolesAcrossScopes($record, $state);
            });
    }

    protected static function permissionsComponent(): Select
    {
        return Select::make('permissions')
            ->label('Direct Permissions')
            ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query): Builder => static::applyPermissionScope($query))
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Assign specific permissions directly. Use roles for standard access control.')
            ->saveRelationshipsUsing(function (Model $record, array $state): void {
                static::syncDirectPermissions($record, $state);
            });
    }

    protected static function syncDirectPermissions(Model $record, array $state): void
    {
        if (! method_exists($record, 'permissions')) {
            return;
        }

        $reference = EloquentRecordReference::fromModel($record);
        $guards = (array) config('authz.guards', ['web']);
        $ids = static::normalizeSelection($state);

        app(RoleAdministrationApi::class)->syncUserPermissions($reference, $ids, (string) ($guards[0] ?? 'web'));
        $record->unsetRelation('permissions');
    }

    protected static function applyPermissionScope(Builder $query): Builder
    {
        $guards = (array) config('authz.guards', ['web']);
        $guard = $guards[0] ?? 'web';

        $table = $query->getModel()->getTable();
        $query->where("{$table}.guard_name", $guard);

        // Permissions are typically global, so we do not scope by team_id
        // unless explicitly configured/customized to do so.
        // For standard setup, we return query as is.

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected static function getRoleOptions(): array
    {
        $guards = (array) config('authz.guards', ['web']);
        $guard = (string) ($guards[0] ?? 'web');

        $registrar = app(PermissionRegistrar::class);
        $teamsKey = $registrar->teamsKey;

        /** @var class-string<Model> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $columns = ['id', 'name'];

        if ($registrar->teams) {
            $columns[] = $teamsKey;
        }

        $rolesQuery = $roleClass::query()
            ->where('guard_name', $guard)
            ->orderBy('name');

        if (static::shouldRestrictToCurrentTeam((bool) $registrar->teams)) {
            $rolesQuery = static::applyCurrentTeamScopeToRoleQuery($rolesQuery, $teamsKey);
        }

        /** @var Collection<int, Model> $roles */
        $roles = $rolesQuery->get($columns);

        $roles = static::filterRolesByScopeMode($roles, $teamsKey, $registrar->teams);

        $scopeLabels = static::resolveScopeLabels($roles, $teamsKey, $registrar->teams);
        $options = [];

        foreach ($roles as $role) {
            $roleId = (string) $role->getKey();
            $roleName = (string) $role->getAttribute('name');
            $scopeId = $registrar->teams ? $role->getAttribute($teamsKey) : null;
            $options[$roleId] = static::formatRoleLabel($roleName, $scopeId, $scopeLabels, $registrar->teams);
        }

        return $options;
    }

    protected static function setRoleStateForRecord(Select $component, ?Model $record): void
    {
        if ($record === null) {
            return;
        }

        $assignedRoleIds = static::getAssignedRoleIds($record);

        if ($assignedRoleIds === []) {
            $component->state([]);

            return;
        }

        $validOptions = array_keys($component->getOptions());
        $filteredRoleIds = array_values(array_intersect($assignedRoleIds, $validOptions));

        $component->state($filteredRoleIds);
    }

    protected static function syncRolesAcrossScopes(Model $record, array $state): void
    {
        if (! method_exists($record, 'syncRoles')) {
            return;
        }

        $reference = EloquentRecordReference::fromModel($record);
        $guards = (array) config('authz.guards', ['web']);

        app(RoleAdministrationApi::class)->syncUserRoles(
            $reference,
            static::normalizeSelection($state),
            (string) ($guards[0] ?? 'web'),
            static::shouldRestrictToCurrentTeam((bool) app(PermissionRegistrar::class)->teams),
            static::getRoleScopeMode(),
        );
        $record->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** @return list<string> */
    private static function normalizeSelection(array $state): array
    {
        return Collection::make($state)
            ->filter(static fn (mixed $id): bool => filled($id))
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected static function getAssignedRoleIds(Model $record): array
    {
        if (! method_exists($record, 'roles')) {
            return [];
        }

        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return $record->roles()
                ->pluck('id')
                ->map(static fn (mixed $roleId): string => (string) $roleId)
                ->values()
                ->all();
        }

        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelMorphKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        $rolePivotKey = (string) $registrar->pivotRole;
        $teamsKey = (string) $registrar->teamsKey;

        $query = DB::table($table)
            ->where($modelMorphKey, $record->getKey())
            ->where('model_type', $record->getMorphClass());

        // Scope to current team when restriction is active; reading cross-tenant
        // pivot rows violates the owner-scoping contract even when the downstream
        // intersection with scoped options would hide them from the UI.
        if (static::shouldRestrictToCurrentTeam((bool) $registrar->teams)) {
            $teamId = getPermissionsTeamId();

            if ($teamId === null) {
                $query->whereNull($teamsKey);
            } else {
                $query->where($teamsKey, $teamId);
            }
        }

        return $query
            ->pluck($rolePivotKey)
            ->map(static fn (mixed $roleId): string => (string) $roleId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Model>  $roles
     * @return Collection<int, Model>
     */
    protected static function filterRolesByScopeMode(Collection $roles, string $teamsKey, bool $teamsEnabled): Collection
    {
        $mode = static::getRoleScopeMode();

        if (! $teamsEnabled || $mode === 'all') {
            return $roles;
        }

        return $roles
            ->filter(function (Model $role) use ($mode, $teamsKey): bool {
                $scopeId = $role->getAttribute($teamsKey);
                $isGlobal = ! is_scalar($scopeId) || (string) $scopeId === '';

                return match ($mode) {
                    'global_only' => $isGlobal,
                    'scoped_only' => ! $isGlobal,
                    default => true,
                };
            })
            ->values();
    }

    /**
     * @return 'all'|'global_only'|'scoped_only'
     */
    protected static function getRoleScopeMode(): string
    {
        $mode = config('filament-authz.user_resource.form.role_scope_mode', 'all');

        if (! is_string($mode) || ! in_array($mode, ['all', 'global_only', 'scoped_only'], true)) {
            return 'all';
        }

        return $mode;
    }

    protected static function shouldRestrictToCurrentTeam(bool $teamsEnabled): bool
    {
        return $teamsEnabled
            && config('filament-authz.scoped_to_tenant', true)
            && ! config('filament-authz.central_app', false);
    }

    protected static function applyCurrentTeamScopeToRoleQuery(Builder $query, string $teamsKey): Builder
    {
        $teamId = getPermissionsTeamId();

        if ($teamId === null) {
            return $query->whereNull($teamsKey);
        }

        return $query->where($teamsKey, $teamId);
    }

    /**
     * @param  Collection<int, Model>  $roles
     * @return array<string, string>
     */
    protected static function resolveScopeLabels(Collection $roles, string $teamsKey, bool $teamsEnabled): array
    {
        if (! $teamsEnabled || ! config('authz.scopes.enabled', false)) {
            return [];
        }

        $scopeIds = $roles
            ->pluck($teamsKey)
            ->filter(static fn (mixed $scopeId): bool => is_scalar($scopeId) && (string) $scopeId !== '')
            ->map(static fn (mixed $scopeId): string => (string) $scopeId)
            ->unique()
            ->values()
            ->all();

        if ($scopeIds === []) {
            return [];
        }

        return AuthzScope::query()
            ->whereIn('id', $scopeIds)
            ->pluck('label', 'id')
            ->mapWithKeys(static fn (mixed $label, mixed $id): array => [(string) $id => (string) $label])
            ->all();
    }

    /**
     * @param  array<string, string>  $scopeLabels
     */
    protected static function formatRoleLabel(string $name, mixed $scopeId, array $scopeLabels, bool $teamsEnabled): string
    {
        if (! $teamsEnabled || ! config('authz.scopes.enabled', false)) {
            return $name;
        }

        if (! is_scalar($scopeId) || (string) $scopeId === '') {
            return "{$name} (Global)";
        }

        $scopeLabel = $scopeLabels[(string) $scopeId] ?? null;

        if (! is_string($scopeLabel) || $scopeLabel === '') {
            return "{$name} (Scoped)";
        }

        return "{$name} ({$scopeLabel})";
    }
}
