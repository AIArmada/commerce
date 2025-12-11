<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopSellingProductsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->select('products.*')
                    ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as total_sold')
                    ->selectRaw('COALESCE(SUM(order_items.line_total), 0) as total_revenue')
                    ->leftJoin('order_items', function ($join): void {
                        $join->on('products.id', '=', 'order_items.buyable_id')
                            ->where('order_items.buyable_type', '=', Product::class);
                    })
                    ->leftJoin('orders', function ($join): void {
                        $join->on('order_items.order_id', '=', 'orders.id')
                            ->whereIn('orders.status', ['completed', 'delivered']);
                    })
                    ->groupBy('products.id')
                    ->orderByDesc('total_sold')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\ImageColumn::make('hero_image')
                    ->label('')
                    ->circular()
                    ->width(50)
                    ->height(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->sku),

                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Units Sold')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('MYR')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),
            ])
            ->heading('Top 10 Selling Products')
            ->description('Based on completed orders');
    }
}
