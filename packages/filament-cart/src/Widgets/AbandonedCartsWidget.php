<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use Akaunting\Money\Money;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget showing carts abandoned during checkout.
 */
final class AbandonedCartsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Abandoned Carts';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Cart ID')
                    ->searchable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('email')
                    ->label('Customer')
                    ->getStateUsing(fn (Cart $record): string => $this->getCustomerEmail($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyMetadataSearch($query, $search)),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn (Cart $record): int => $this->getItemsCount($record)),

                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->getStateUsing(fn (Cart $record): string => $this->getCartValue($record)),

                Tables\Columns\TextColumn::make('checkout_abandoned_at')
                    ->label('Abandoned')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('time_since_abandonment')
                    ->label('Age')
                    ->getStateUsing(fn (Cart $record): string => $this->getTimeSinceAbandonment($record)),
            ])
            ->defaultSort('checkout_abandoned_at', 'desc')
            ->actions([
                Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Cart $record): string => CartResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No abandoned carts')
            ->emptyStateDescription('Great! There are no currently abandoned carts.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<Cart>
     */
    protected function getTableQuery(): Builder
    {
        return Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '>=', now()->subDays(7));
    }

    private function getCustomerEmail(Cart $record): string
    {
        $metadata = $record->metadata ?? [];

        return $metadata['customer_email'] ?? $metadata['email'] ?? 'Unknown';
    }

    /**
     * Apply a driver-aware metadata search clause.
     *
     * PostgreSQL JSON/JSONB columns cannot be searched with LIKE directly,
     * so cast metadata to text first.
     *
     * @param  Builder<Cart>  $query
     */
    private function applyMetadataSearch(Builder $query, string $search): Builder
    {
        $needle = "%{$search}%";
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereRaw('CAST(metadata AS TEXT) ILIKE ?', [$needle]);
        }

        return $query->where('metadata', 'like', $needle);
    }

    private function getItemsCount(Cart $record): int
    {
        $items = $record->items ?? [];

        if (is_string($items)) {
            $items = json_decode($items, true) ?? [];
        }

        return count($items);
    }

    private function getCartValue(Cart $record): string
    {
        $currency = mb_strtoupper($record->currency ?: config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}((int) $record->subtotal);
    }

    private function getTimeSinceAbandonment(Cart $record): string
    {
        if (! $record->checkout_abandoned_at) {
            return 'Unknown';
        }

        return $record->checkout_abandoned_at->diffForHumans(['short' => true]);
    }
}
