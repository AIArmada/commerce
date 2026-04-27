<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartItemResource\Tables;

use AIArmada\FilamentCart\Actions\ApplyConditionAction;
use Akaunting\Money\Money;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
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

                // IconColumn::make('conditions')
                //     ->label('Conditions')
                //     ->icon(fn ($state): \Filament\Support\Icons\Heroicon =>
                //         (is_array($state) ? count($state) > 0 : !empty($state))
                //             ? \Filament\Support\Icons\Heroicon::OutlinedCheckCircle
                //             : \Filament\Support\Icons\Heroicon::OutlinedXCircle
                //     )
                //     ->toggleable()
                //     ->sortable(),

                // IconColumn::make('attributes')
                //     ->label('Attributes')
                //     ->icon(fn ($state): \Filament\Support\Icons\Heroicon =>
                //         (is_array($state) ? count($state) > 0 : !empty($state))
                //             ? \Filament\Support\Icons\Heroicon::OutlinedCheckCircle
                //             : \Filament\Support\Icons\Heroicon::OutlinedXCircle
                //     )
                //     ->toggleable()
                //     ->sortable(),

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
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->withConditions()),

                Filter::make('no_conditions')
                    ->label('No Conditions')
                    ->query(fn (Builder $query): Builder => /** @phpstan-ignore method.notFound */ $query->withoutConditions()),

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
            ->actions([
                ApplyConditionAction::makeForItem(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    private static function resolveCurrency(): string
    {
        return mb_strtoupper(config('cart.money.default_currency', 'USD'));
    }

    private static function formatMoney(int $amount, ?string $currency = null): string
    {
        $resolvedCurrency = is_string($currency) && $currency !== '' ? mb_strtoupper($currency) : self::resolveCurrency();

        return (string) Money::{$resolvedCurrency}($amount);
    }

    private static function toMinorUnits(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
