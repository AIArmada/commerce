<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartItemResource\Tables;

use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotItem;
use AIArmada\Cart\Support\CartMoney;
use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CartItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cart.identifier')
                    ->label('Cart')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => self::formatMoney((int) ($state ?? 0), $record->cart->currency ?? null))
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state, $record) => self::formatMoney((int) ($state ?? 0), $record->cart->currency ?? null))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('instance')
                    ->label('Instance')
                    ->options([
                        'default' => 'Default',
                        'wishlist' => 'Wishlist',
                        'comparison' => 'Comparison',
                        'quote' => 'Quote',
                        'bulk' => 'Bulk Order',
                        'subscription' => 'Subscription',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $instance): Builder => $query->whereHas('cart', function ($q) use ($instance): void {
                                $q->where('instance', $instance);
                            })
                        );
                    }),

                Filter::make('has_conditions')
                    ->label('Has Conditions')
                    ->query(self::withConditions(...)),

                Filter::make('no_conditions')
                    ->label('No Conditions')
                    ->query(self::withoutConditions(...)),

                Filter::make('price_range')
                    ->form([
                        TextInput::make('price_from')
                            ->label('Price From')
                            ->numeric()
                            ->prefix(self::resolveCurrency()),
                        TextInput::make('price_to')
                            ->label('Price To')
                            ->numeric()
                            ->prefix(self::resolveCurrency()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'] ?? null,
                                fn (Builder $query, $price): Builder => $query->where('price', '>=', self::toMinorUnits($price)),
                            )
                            ->when(
                                $data['price_to'] ?? null,
                                fn (Builder $query, $price): Builder => $query->where('price', '<=', self::toMinorUnits($price)),
                            );
                    }),

                Filter::make('quantity_range')
                    ->form([
                        TextInput::make('quantity_from')
                            ->label('Quantity From')
                            ->numeric(),
                        TextInput::make('quantity_to')
                            ->label('Quantity To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['quantity_from'] ?? null,
                                fn (Builder $query, $qty): Builder => $query->where('quantity', '>=', $qty),
                            )
                            ->when(
                                $data['quantity_to'] ?? null,
                                fn (Builder $query, $qty): Builder => $query->where('quantity', '<=', $qty),
                            );
                    }),
            ])
            ->recordActions([
                ApplyConditionAction::makeForItem(),
            ])
            ->toolbarActions([])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('cart'))
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    /**
     * @param  Builder<CartSnapshotItem>  $query
     * @return Builder<CartSnapshotItem>
     */
    private static function withConditions(Builder $query): Builder
    {
        return $query->withConditions();
    }

    /**
     * @param  Builder<CartSnapshotItem>  $query
     * @return Builder<CartSnapshotItem>
     */
    private static function withoutConditions(Builder $query): Builder
    {
        return $query->withoutConditions();
    }

    private static function resolveCurrency(): string
    {
        return CartMoney::currency();
    }

    private static function formatMoney(int $amount, ?string $currency = null): string
    {
        $resolvedCurrency = is_string($currency) && $currency !== '' ? $currency : self::resolveCurrency();

        return CartMoney::formatMinor($amount, $resolvedCurrency);
    }

    private static function toMinorUnits(mixed $amount): int
    {
        return CartMoney::minorFromDecimal((string) $amount);
    }
}
