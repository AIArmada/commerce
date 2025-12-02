<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

final class LowInventoryAlertsWidget extends TableWidget
{
    protected static ?int $sort = 20;

    protected static ?string $heading = 'Low Inventory Alerts';

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return config('filament-inventory.features.low_stock_widget', true);
    }

    public function table(Table $table): Table
    {
        $aggregator = app(InventoryStatsAggregator::class);

        return $table
            ->query($aggregator->getLowStockQuery())
            ->columns([
                TextColumn::make('location.name')
                    ->label('Location')
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('inventoryable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('info'),

                TextColumn::make('inventoryable_id')
                    ->label('Product ID')
                    ->limit(8)
                    ->copyable(),

                TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric()
                    ->alignCenter()
                    ->color('warning'),

                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn ($record): int => $record->quantity_on_hand - $record->quantity_reserved)
                    ->numeric()
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('reorder_point')
                    ->label('Reorder Point')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('deficit')
                    ->label('Deficit')
                    ->state(fn ($record): int => max(0, $record->reorder_point - ($record->quantity_on_hand - $record->quantity_reserved)))
                    ->numeric()
                    ->alignCenter()
                    ->color('danger')
                    ->weight('bold'),
            ])
            ->defaultSort('deficit', 'desc')
            ->paginated([5, 10, 25])
            ->striped()
            ->emptyStateHeading('No Low Stock Items')
            ->emptyStateDescription('All inventory levels are above their reorder points.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
