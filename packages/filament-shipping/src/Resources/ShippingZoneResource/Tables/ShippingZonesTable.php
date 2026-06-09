<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class ShippingZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->badge()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'country' => 'success',
                        'state' => 'info',
                        'postcode' => 'warning',
                        'radius' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->boolean(),

                IconColumn::make('active')
                    ->boolean(),

                TextColumn::make('rates_count')
                    ->label('Rates')
                    ->counts('rates'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'postcode' => 'Postcode',
                        'radius' => 'Radius',
                    ]),

                TernaryFilter::make('active'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }
}
