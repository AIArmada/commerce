<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AddressStateTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.iso2')
                    ->label('ISO2')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('label')
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
            ])
            ->defaultSort('country.name')
            ->paginated([10, 25, 50, 100]);
    }
}
