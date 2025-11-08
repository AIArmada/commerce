<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Resources\RoleResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\PermissionRegistrar;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Permissions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('guard_name')->badge(),
            ])
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('guard_name', $this->ownerRecord->guard_name))
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->recordActions([
                \Filament\Actions\DetachAction::make()
                    ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make()
                        ->after(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions()),
                ]),
            ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('permissions')
                ->multiple()
                ->relationship('permissions', 'name')
                ->preload(),
        ]);
    }
}
