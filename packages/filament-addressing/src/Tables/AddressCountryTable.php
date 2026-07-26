<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AddressCountryTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('iso2')
                    ->label('ISO2')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('iso3')
                    ->label('ISO3')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currencies.code')
                    ->label('Currencies')
                    ->toggleable(),
                TextColumn::make('phone_code')
                    ->label('Phone')
                    ->toggleable(),
                TextColumn::make('region')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('region')
                    ->options(fn (): array => self::getRegionOptions()),
                SelectFilter::make('currencies')
                    ->label('Currency')
                    ->relationship('currencies', 'code'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100]);
    }

    private static function getRegionOptions(): array
    {
        $countryClass = config('filament-addressing.resources.countries.model', AddressCountry::class);

        return $countryClass::query()
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region', 'region')
            ->toArray();
    }
}
