<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('line1')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('postcode')
                    ->searchable(),
                TextColumn::make('country_code')
                    ->label('Country')
                    ->sortable(),
                TextColumn::make('country')
                    ->toggleable(),
                IconColumn::make('validation_status')
                    ->label('Valid')
                    ->boolean()
                    ->state(fn ($record): bool => $record->validation_status === 'verified')
                    ->toggleable(),
                TextColumn::make('provider')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
