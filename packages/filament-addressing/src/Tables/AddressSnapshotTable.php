<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressSnapshotTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('snapshotable_type')
                    ->label('Source Type')
                    ->toggleable(),
                TextColumn::make('snapshotable_id')
                    ->label('Source ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('reason')
                    ->badge(),
                TextColumn::make('formatted_address')
                    ->label('Address')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('country')
                    ->toggleable(),
                TextColumn::make('state')
                    ->toggleable(),
                TextColumn::make('city')
                    ->toggleable(),
                TextColumn::make('postcode')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
