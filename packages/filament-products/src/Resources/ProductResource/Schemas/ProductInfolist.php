<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Schemas;

use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Models\Product;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Product Details')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Name'),
                        TextEntry::make('sku')
                            ->label('SKU')
                            ->copyable(),
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                        TextEntry::make('supports_variants')
                            ->label('Supports Variants')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        TextEntry::make('tracks_inventory')
                            ->label('Tracks Inventory')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                    ])
                    ->columns(6),

                Section::make('Pricing')
                    ->schema([
                        TextEntry::make('price')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100),
                        TextEntry::make('compare_price')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                            ->visible(fn ($record) => $record->compare_price),
                        TextEntry::make('cost')
                            ->money(fn (Product $record): string => $record->currency, divideBy: 100)
                            ->visible(fn ($record) => $record->cost),
                    ])
                    ->columns(3),

                Section::make('Calculated Pricing')
                    ->schema([
                        TextEntry::make('calculated_price')
                            ->label('Calculated Price')
                            ->state(function (Product $record): string {
                                $result = ProductResource::calculatePriceResult($record, null);

                                if (! $result) {
                                    return '—';
                                }

                                return ProductResource::formatCurrency($result->finalPrice, $record->currency);
                            }),
                        TextEntry::make('calculated_discount')
                            ->label('Discount')
                            ->state(function (Product $record): string {
                                $result = ProductResource::calculatePriceResult($record, null);

                                if (! $result || $result->discountAmount <= 0) {
                                    return 'No discount';
                                }

                                $amount = ProductResource::formatCurrency($result->discountAmount, $record->currency);

                                return "{$amount} ({$result->discountPercentage}%)";
                            }),
                        TextEntry::make('calculated_source')
                            ->label('Applied Rule')
                            ->state(function (Product $record): string {
                                $result = ProductResource::calculatePriceResult($record, null);

                                return $result?->promotionName
                                    ?? $result?->priceListName
                                    ?? $result?->tierDescription
                                    ?? $result?->discountSource
                                    ?? 'Base Price';
                            }),
                    ])
                    ->columns(3),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
