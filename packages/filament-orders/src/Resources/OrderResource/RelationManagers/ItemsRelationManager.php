<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Order Items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('MYR', divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('MYR', divideBy: 100)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tax_amount')
                    ->label('Tax')
                    ->money('MYR', divideBy: 100)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR', divideBy: 100)
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
