<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\Authz\Features\Impersonation\Api\ImpersonationApi;
use AIArmada\Authz\Features\Impersonation\Queries\ImpersonationScopeGuard;
use AIArmada\Authz\Support\UserRoleChecker;
use AIArmada\FilamentAuthz\Facades\FilamentAuthz;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use AIArmada\FilamentAuthz\Tables\Actions\ImpersonateTableAction;
use DateTimeZone;
use Filament\Actions;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class UserResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        $model = config('filament-authz.user_resource.model');

        if (is_string($model) && $model !== '') {
            return $model;
        }

        $guard = config('authz.guards.0', 'web');
        $provider = config("auth.guards.{$guard}.provider");
        $configured = config("auth.providers.{$provider}.model");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return mb_rtrim(app()->getNamespace(), '\\') . '\\Models\\User';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (method_exists(static::getModel(), 'roles')) {
            $query->with('roles');
        }

        return ImpersonationScopeGuard::applyScopeToUserQuery($query);
    }

    public static function canViewAny(): bool
    {
        return static::hasAuthzDirectoryAccess() && static::checkAbility('viewAny');
    }

    public static function canView(Model $record): bool
    {
        return static::hasAuthzDirectoryAccess()
            && static::checkAbility('view')
            && static::isRecordInScope($record);
    }

    public static function canCreate(): bool
    {
        return static::hasAuthzDirectoryAccess() && static::checkAbility('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::hasAuthzDirectoryAccess()
            && static::checkAbility('update')
            && static::isRecordInScope($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::hasAuthzDirectoryAccess()
            && static::checkAbility('delete')
            && static::isRecordInScope($record);
    }

    protected static function isRecordInScope(Model $record): bool
    {
        if (! $record->exists || $record->getKey() === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);

        if (! config('authz.scopes.enforce', true) || ! $registrar->teams) {
            return true;
        }

        $teamId = getPermissionsTeamId();
        $teamsKey = (string) $registrar->teamsKey;

        if ($record->relationLoaded('roles')) {
            $roles = $record->getRelationValue('roles');

            if ($roles instanceof Collection) {
                foreach ($roles as $role) {
                    $pivot = $role->getRelationValue('pivot');

                    if (! $pivot instanceof Model || ! array_key_exists($teamsKey, $pivot->getAttributes())) {
                        continue;
                    }

                    $pivotTeam = $pivot->getAttribute($teamsKey);

                    if ($teamId === null ? $pivotTeam === null : (string) $pivotTeam === (string) $teamId) {
                        return true;
                    }
                }
            }
        }

        if ($record instanceof Authenticatable) {
            return app(ImpersonationApi::class)->canAccessTarget($record);
        }

        return static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    protected static function hasAuthzDirectoryAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        $superAdminRole = (string) config('authz.super_admin_role', '');

        if ($superAdminRole !== '' && UserRoleChecker::hasGlobalRole($user, $superAdminRole)) {
            return true;
        }

        return $user->can('role.viewAny')
            || $user->can('permission.viewAny')
            || $user->can('settings.manage');
    }

    protected static function checkAbility(string $action): bool
    {
        $user = Auth::user();

        if (! $user instanceof Authorizable) {
            return false;
        }

        $superAdminRole = (string) config('authz.super_admin_role', '');

        if ($superAdminRole !== '' && UserRoleChecker::hasGlobalRole($user, $superAdminRole)) {
            return true;
        }

        $ability = FilamentAuthz::buildPermissionKey('User', $action);

        return $user->can($ability);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.user_resource.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.user_resource.navigation.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.user_resource.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return (string) config('filament-authz.user_resource.slug', 'authz/users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('User Details')
                ->schema(static::getDefaultFormFields())
                ->columns(2),
            ...UserAuthzForm::components(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ImpersonateTableAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->striped()
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Create users and assign roles or permissions.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        $configuredRelations = config('filament-authz.user_resource.relation_managers', []);

        if (! is_array($configuredRelations)) {
            return [];
        }

        $relations = [];

        foreach ($configuredRelations as $relationClass) {
            if (is_string($relationClass) && is_a($relationClass, RelationManager::class, true)) {
                $relations[] = $relationClass;
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * @return list<Forms\Components\Field>
     */
    protected static function getDefaultFormFields(): array
    {
        $fields = (array) config('filament-authz.user_resource.form.fields', ['name', 'email', 'password']);
        $components = [];

        foreach ($fields as $field) {
            $components[] = match ($field) {
                'name' => Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                'email' => Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                'password' => Forms\Components\TextInput::make('password')
                    ->password()
                    ->rule(Password::defaults())
                    ->dehydrateStateUsing(function (?string $state): ?string {
                        if ($state === null || $state === '') {
                            return null;
                        }

                        return Hash::make($state);
                    })
                    ->dehydrated(function (?string $state): bool {
                        return filled($state);
                    })
                    ->required(function (string $operation): bool {
                        return $operation === 'create';
                    })
                    ->maxLength(255),
                'timezone' => Forms\Components\Select::make('timezone')
                    ->searchable()
                    ->options(static function (): array {
                        $timezones = DateTimeZone::listIdentifiers();

                        return array_combine($timezones, $timezones);
                    })
                    ->default(static fn (): string => is_string(config('app.timezone')) && config('app.timezone') !== ''
                        ? config('app.timezone')
                        : 'UTC')
                    ->nullable(),
                'email_verified_at' => Forms\Components\DateTimePicker::make('email_verified_at')
                    ->nullable(),
                'phone_verified_at' => Forms\Components\DateTimePicker::make('phone_verified_at')
                    ->nullable(),
                default => Forms\Components\TextInput::make((string) $field)
                    ->maxLength(255),
            };
        }

        return $components;
    }
}
