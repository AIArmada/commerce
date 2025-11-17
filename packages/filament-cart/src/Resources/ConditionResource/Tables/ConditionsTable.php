<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\ConditionResource\Tables;

use Akaunting\Money\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class ConditionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Condition Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'discount' => 'success',
                        'tax', 'fee', 'surcharge' => 'warning',
                        'shipping' => 'info',
                        'credit' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Target')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'subtotal' => 'Cart Subtotal',
                        'total' => 'Cart Total',
                        'item' => 'Individual Items',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Value')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (string $state): string => str_contains($state, '%') ? 'info' : 'secondary')
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
                    ->trueIcon(Heroicon::OutlinedGlobeAsiaAustralia)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->tooltip('Applied automatically to every cart when active')
                    ->toggleable(),

                TextColumn::make('parsed_value')
                    ->label('Parsed Value')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Created')
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
                        'fee' => 'Fee',
                        'tax' => 'Tax',
                        'shipping' => 'Shipping',
                        'surcharge' => 'Surcharge',
                        'credit' => 'Credit',
                        'adjustment' => 'Adjustment',
                    ]),

                SelectFilter::make('target')
                    ->options([
                        'cart@cart_subtotal/aggregate' => 'Cart Subtotal',
                        'cart@grand_total/aggregate' => 'Cart Total',
                        'items@item_discount/per-item' => 'Individual Items',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),

                SelectFilter::make('is_discount')
                    ->label('Discount')
                    ->options([
                        1 => 'Discounts Only',
                        0 => 'Non-Discounts Only',
                    ]),

                SelectFilter::make('is_percentage')
                    ->label('Percentage-Based')
                    ->options([
                        1 => 'Percentage Only',
                        0 => 'Fixed Amount Only',
                    ]),

                SelectFilter::make('is_dynamic')
                    ->label('Dynamic Conditions')
                    ->options([
                        1 => 'Dynamic Only',
                        0 => 'Static Only',
                    ]),

                SelectFilter::make('is_charge')
                    ->label('Charges')
                    ->options([
                        1 => 'Charges Only',
                        0 => 'Non-Charges Only',
                    ]),

                SelectFilter::make('is_global')
                    ->label('Global')
                    ->options([
                        1 => 'Global Only',
                        0 => 'Non-Global Only',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->poll('30s');
    }
}
