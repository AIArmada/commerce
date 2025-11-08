<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources;

use AIArmada\FilamentPermissions\Resources\UserResource\Pages;
use AIArmada\FilamentPermissions\Resources\UserResource\RelationManagers;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = null; // assigned dynamically

    public static function getModel(): string
    {
        return (string) config('filament-permissions.user_model', \App\Models\User::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-permissions.navigation.group');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-permissions.navigation.icons.users');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-permissions.navigation.sort');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->can('user.viewAny') || $user?->hasRole(config('filament-permissions.super_admin_role'));
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('User')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('email')->searchable()->sortable(),
            TextColumn::make('created_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->actions([
            Tables\Actions\EditAction::make()->authorize(fn (Model $record) => auth()->user()?->can('user.update')),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RolesRelationManager::class,
            RelationManagers\PermissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
