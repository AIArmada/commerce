<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AreaRolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Address Roles';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('role')
            ->columns([
                TextColumn::make('role')->badge()->searchable()->sortable(),
                TextColumn::make('country_code')->label('Country')->badge(),
                TextColumn::make('source')->badge(),
                IconColumn::make('is_primary')->label('Primary')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('role')
            ->paginated([10, 25, 50]);
    }
}
