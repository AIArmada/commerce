<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use AIArmada\Orders\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class LatestOrders extends BaseWidget
{
    protected static ?int $sort = -1;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        // 'lg' => 1,
    ];

    protected static ?string $heading = 'Latest Orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('customer_id')
                    ->label('Customer')
                    ->limit(20)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Order $record): string => $record->status->label())
                    ->color(fn (Order $record): string => $record->status->color()),

                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->since()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
