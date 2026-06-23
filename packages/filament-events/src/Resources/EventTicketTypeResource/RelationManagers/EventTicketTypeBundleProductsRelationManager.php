<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventTicketTypeResource\RelationManagers;

use AIArmada\Events\Enums\BundleInclusionMode;
use AIArmada\Events\Models\EventTicketTypeProduct;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class EventTicketTypeBundleProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'bundleProducts';

    protected static ?string $title = 'Bundle Products';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_id')
                    ->label('Product')
                    ->formatStateUsing(function (EventTicketTypeProduct $record): string {
                        $productClass = config('events.integrations.product_model');

                        if ($productClass === null || $record->product_id === null) {
                            return $record->product_id ?? '-';
                        }

                        $product = $productClass::find($record->product_id);

                        return $product?->name ?? $record->product_id;
                    }),
                Tables\Columns\TextColumn::make('variant_id')
                    ->label('Variant')
                    ->formatStateUsing(function (EventTicketTypeProduct $record): string {
                        $variantClass = config('events.integrations.variant_model');

                        if ($variantClass === null || $record->variant_id === null) {
                            return $record->variant_id ?? '-';
                        }

                        $variant = $variantClass::find($record->variant_id);

                        return $variant?->name ?? $record->variant_id;
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric(),
                Tables\Columns\TextColumn::make('inclusion_mode')
                    ->badge()
                    ->formatStateUsing(fn (BundleInclusionMode $state): string => $state->label()),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->label('Sort'),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
