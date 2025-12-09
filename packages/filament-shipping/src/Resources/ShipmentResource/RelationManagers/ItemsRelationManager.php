<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(),

                Tables\Columns\TextColumn::make('weight')
                    ->formatStateUsing(fn ($state) => number_format($state / 1000, 2) . ' kg'),

                Tables\Columns\TextColumn::make('declared_value')
                    ->money('MYR', divideBy: 100),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }
}
