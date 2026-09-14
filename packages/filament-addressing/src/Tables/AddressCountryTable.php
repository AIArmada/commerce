<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AddressCountryTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['currencies']))
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
                    ->options(fn (): array => AddressingFilterOptions::countryRegions()),
                SelectFilter::make('currencies')
                    ->label('Currency')
                    ->relationship('currencies', 'code'),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50, 100]);
    }
}
