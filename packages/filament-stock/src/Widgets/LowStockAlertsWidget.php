<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Widgets;

use AIArmada\FilamentStock\Support\StockableTypeRegistry;
use AIArmada\Stock\Models\StockTransaction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Lazy;

/**
 * Displays items with low stock levels based on configured threshold.
 */
#[Lazy]
final class LowStockAlertsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    /**
     * Get the count of low stock items for navigation badge.
     */
    public static function getLowStockCount(): int
    {
        /** @var int $threshold */
        $threshold = config('filament-stock.low_stock_threshold', 10);

        return (int) StockTransaction::query()
            ->select([
                'stockable_type',
                'stockable_id',
                DB::raw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as current_stock'),
            ])
            ->groupBy('stockable_type', 'stockable_id')
            ->havingRaw('current_stock <= ?', [$threshold])
            ->count();
    }

    public function getTableHeading(): string
    {
        return 'Low Stock Alerts';
    }

    public function getTableDescription(): string
    {
        $threshold = $this->getLowStockThreshold();

        return "Items with stock levels at or below {$threshold} units";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->emptyStateHeading('No Low Stock Items')
            ->emptyStateDescription('All monitored items have sufficient stock levels.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('stockable_type')
                    ->label('Item Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('stockable_id')
                    ->label('Item ID')
                    ->formatStateUsing(function (string $state, $record): string {
                        $registry = app(StockableTypeRegistry::class);
                        $label = $registry->resolveLabelForKey(
                            $record->stockable_type,
                            $state
                        );

                        return $label ?? $state;
                    })
                    ->searchable(),

                TextColumn::make('current_stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $this->getStockLevelColor($state)),

                TextColumn::make('last_movement')
                    ->label('Last Movement')
                    ->dateTime('M d, Y g:i A')
                    ->since()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn ($record): string => $this->getStockStatus($record->current_stock))
                    ->color(fn (string $state): string => match ($state) {
                        'Critical' => 'danger',
                        'Low' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('stockable_type')
                    ->label('Item Type')
                    ->options(fn () => $this->getStockableTypeOptions()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'critical' => 'Critical (0 stock)',
                        'low' => 'Low Stock',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'critical' => $query->having('current_stock', '=', 0),
                            'low' => $query->having('current_stock', '>', 0),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('view_transactions')
                    ->label('View History')
                    ->icon('heroicon-o-clock')
                    ->url(fn ($record): string => route('filament.admin.resources.stock-transactions.index', [
                        'tableFilters[stockable_type][value]' => $record->stockable_type,
                        'tableFilters[stockable_id][value]' => $record->stockable_id,
                    ]))
                    ->openUrlInNewTab(),

                Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn ($record): string => route('filament.admin.resources.stock-transactions.create', [
                        'stockable_type' => $record->stockable_type,
                        'stockable_id' => $record->stockable_id,
                    ])),
            ])
            ->defaultSort('current_stock', 'asc')
            ->poll('30s');
    }

    /**
     * Get the query for low stock items.
     */
    protected function getTableQuery(): Builder
    {
        $threshold = $this->getLowStockThreshold();

        // Subquery to calculate current stock levels
        return StockTransaction::query()
            ->select([
                'stockable_type',
                'stockable_id',
                DB::raw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as current_stock'),
                DB::raw('MAX(transaction_date) as last_movement'),
            ])
            ->groupBy('stockable_type', 'stockable_id')
            ->having('current_stock', '<=', $threshold);
    }

    /**
     * Get the configured low stock threshold.
     */
    protected function getLowStockThreshold(): int
    {
        /** @var int $threshold */
        $threshold = config('filament-stock.low_stock_threshold', 10);

        return $threshold;
    }

    /**
     * Get color based on stock level.
     */
    protected function getStockLevelColor(int $stock): string
    {
        if ($stock <= 0) {
            return 'danger';
        }

        $threshold = $this->getLowStockThreshold();

        if ($stock <= $threshold / 2) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get status label based on stock level.
     */
    protected function getStockStatus(int $stock): string
    {
        return $stock <= 0 ? 'Critical' : 'Low';
    }

    /**
     * Get stockable type options for filter.
     *
     * @return array<string, string>
     */
    protected function getStockableTypeOptions(): array
    {
        return StockTransaction::query()
            ->distinct()
            ->pluck('stockable_type')
            ->mapWithKeys(fn (string $type): array => [
                $type => class_basename($type),
            ])
            ->toArray();
    }
}
