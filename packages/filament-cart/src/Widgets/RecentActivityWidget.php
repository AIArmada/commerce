<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\Cart\Snapshots\CartSnapshot as Cart;
use AIArmada\Cart\Support\CartMoney;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recent cart activity feed widget.
 */
class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Activity';

    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getActivityQuery())
            ->columns([
                Tables\Columns\TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->session_id),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'checkout' => 'success',
                        'active' => 'info',
                        'abandoned' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Value')
                    ->formatStateUsing(fn ($state, $record): string => CartMoney::formatMinor(
                        (int) $state,
                        is_string($record->currency ?? null) ? $record->currency : null,
                    )),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([10]);
    }

    /**
     * @return Builder<Cart>
     */
    private function getActivityQuery(): Builder
    {
        $query = Cart::query()
            ->selectRaw("
                id,
                identifier as session_id,
                CASE
                    WHEN checkout_abandoned_at IS NOT NULL THEN 'abandoned'
                    WHEN checkout_started_at IS NOT NULL THEN 'checkout'
                    ELSE 'active'
                END as status,
                items_count,
                currency,
                total as total_cents,
                updated_at
            ")
            ->orderByDesc('updated_at')
            ->limit(50);

        $query->forOwner(includeGlobal: Cart::includeGlobalRecords());

        return $query;
    }
}
