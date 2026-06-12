<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AddressCountryTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('iso2')
                    ->label('ISO2')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('iso3')
                    ->label('ISO3')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('official_name')
                    ->label('Official Name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('entity_type')
                    ->badge()
                    ->toggleable(),
                IconColumn::make('is_independent')
                    ->label('Independent')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('default_currency_code')
                    ->label('Currency')
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
                SelectFilter::make('entity_type')
                    ->options([
                        'country' => 'Country',
                        'territory' => 'Territory',
                        'dependent' => 'Dependent',
                        'special' => 'Special',
                        'disputed' => 'Disputed',
                    ]),
                SelectFilter::make('is_independent')
                    ->label('Independent')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
                SelectFilter::make('region')
                    ->options(fn (): array => self::getRegionOptions()),
                SelectFilter::make('default_currency_code')
                    ->label('Currency')
                    ->options(fn (): array => self::getCurrencyOptions()),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100]);
    }

    private static function getRegionOptions(): array
    {
        return AddressCountry::query()
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region', 'region')
            ->toArray();
    }

    private static function getCurrencyOptions(): array
    {
        $models = AddressCountry::query()
            ->whereNotNull('default_currency_code')
            ->distinct()
            ->orderBy('default_currency_code')
            ->pluck('default_currency_code', 'default_currency_code')
            ->toArray();

        return array_combine($models, $models);
    }
}
