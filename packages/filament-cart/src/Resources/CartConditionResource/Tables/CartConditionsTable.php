<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartConditionResource\Tables;

use Akaunting\Money\Money;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CartConditionsTable
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
                    ->label('Condition Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'discount' => 'success',
                        'tax' => 'warning',
                        'fee' => 'danger',
                        'shipping' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Target')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->alignEnd()
                    ->formatStateUsing(fn (?string $state) => match (true) {
                        $state === null => Money::MYR(0),
                        str_contains($state, '%') => $state,
                        default => (str_starts_with($state, '+')
                            ? '+'.Money::MYR(mb_ltrim($state, '+'))
                            : Money::MYR($state))
                    })
                    ->sortable(),

                TextColumn::make('operator')
                    ->label('Operator')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        '+' => 'success',
                        '-' => 'danger',
                        '*' => 'info',
                        '/' => 'warning',
                        '%' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_charge')
                    ->label('Charge')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_discount')
                    ->label('Discount')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_percentage')
                    ->label('Percentage')
                    ->boolean()
                    ->trueIcon('heroicon-o-percent-badge')
                    ->falseIcon('heroicon-o-currency-dollar')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_dynamic')
                    ->label('Dynamic')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-asia-australia')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->toggleable(),

                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cart' => 'primary',
                        'Item' => 'secondary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('order')
                    ->label('Order')
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
                SelectFilter::make('type')
                    ->options([
                        'discount' => 'Discount',
                        'tax' => 'Tax',
                        'fee' => 'Fee',
                        'shipping' => 'Shipping',
                    ]),

                SelectFilter::make('target')
                    ->options([
                        'cart@cart_subtotal/aggregate' => 'Cart Subtotal',
                        'cart@grand_total/aggregate' => 'Cart Total',
                        'items@item_discount/per-item' => 'Item Level',
                    ]),

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

                Filter::make('cart_level')
                    ->label('Cart Level')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->cartLevel()),

                Filter::make('item_level')
                    ->label('Item Level')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->itemLevel()),

                Filter::make('discounts')
                    ->label('Discounts Only')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->discounts()),

                Filter::make('taxes')
                    ->label('Taxes Only')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->taxes()),

                Filter::make('fees')
                    ->label('Fees Only')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->fees()),

                Filter::make('shipping')
                    ->label('Shipping Only')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->shipping()),

                Filter::make('global')
                    ->label('Global Only')
                    ->query(fn (Builder $query): Builder => $query->where('is_global', true)),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}
