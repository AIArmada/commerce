<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AddressCityTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('country.iso2')
                    ->label('ISO2')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state.name')
                    ->label('State')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('latitude')
                    ->numeric(5)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->numeric(5)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('state_id')
                    ->label('State')
                    ->relationship('state', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('country.name')
            ->paginated([10, 25, 50, 100]);
    }
}
