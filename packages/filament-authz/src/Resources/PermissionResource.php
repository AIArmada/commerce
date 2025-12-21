<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Resources;

use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers;
use AIArmada\FilamentAuthz\Models\Permission;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class PermissionResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return config('permission.models.permission', Permission::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-authz.navigation.icons.permissions');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-authz.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return (bool) ($user->can('permission.viewAny') || $user->hasRole(config('filament-authz.super_admin_role')));
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Permission Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                        $guard = $get('guard_name');

                        if (is_string($guard) && $guard !== '') {
                            $rule->where('guard_name', $guard);
                        }

                        return $rule;
                    }),
                Forms\Components\Select::make('guard_name')
                    ->options(array_combine(config('filament-authz.guards'), config('filament-authz.guards')))
                    ->default(config('filament-authz.guards.0'))
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('guard_name')->badge()->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('guard_name = web')->query(fn (Builder $q) => $q->where('guard_name', 'web')),
        ])->actions([
            Actions\EditAction::make()->authorize(fn (Permission $record) => auth()->user()?->can('permission.update')),
            Actions\DeleteAction::make()->authorize(fn (Permission $record) => auth()->user()?->can('permission.delete')),
        ])->bulkActions([
            Actions\DeleteBulkAction::make()->authorize(fn () => auth()->user()?->can('permission.delete')),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
