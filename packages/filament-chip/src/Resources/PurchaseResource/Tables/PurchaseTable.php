<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Tables;

use AIArmada\FilamentChip\Models\ChipPurchase;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class PurchaseTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->striped()
            ->contentGrid([
                'md' => 1,
                'xl' => 2,
            ])
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('reference')
                            ->label('Reference')
                            ->icon('heroicon-o-tag')
                            ->iconColor('primary')
                            ->copyable()
                            ->searchable()
                            ->wrap(),
                        TextColumn::make('client_email')
                            ->label('Client Email')
                            ->icon('heroicon-o-envelope')
                            ->iconColor('primary')
                            ->searchable()
                            ->wrap()
                            ->placeholder('—'),
                        TextColumn::make('purchase.reference')
                            ->label('Invoice Reference')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ])->carded(),
                    Panel::make([
                        Stack::make([
                            TextColumn::make('formatted_total')
                                ->label('Grand Total')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),
                            TextColumn::make('purchase.subtotal.amount')
                                ->label('Subtotal')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    $record->purchase['subtotal']['currency'] ?? $record->purchase['currency'] ?? null,
                                ))
                                ->icon('heroicon-o-banknotes'),
                            TextColumn::make('purchase.taxes.amount')
                                ->label('Taxes')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    $record->purchase['taxes']['currency'] ?? $record->purchase['currency'] ?? null,
                                ))
                                ->icon('heroicon-o-sparkles')
                                ->placeholder('—'),
                        ])->carded(), // @phpstan-ignore method.notFound
                    ])->softShadow(), // @phpstan-ignore method.notFound
                    Stack::make([
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (ChipPurchase $record): string => $record->statusColor())
                            ->formatStateUsing(fn (ChipPurchase $record): string => $record->statusBadge()),
                        TextColumn::make('created_on')
                            ->label('Created')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->since()
                            ->icon('heroicon-o-clock'),
                        TextColumn::make('due')
                            ->label('Due')
                            ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                            ->placeholder('—')
                            ->icon('heroicon-o-calendar'),
                        IconColumn::make('is_test')
                            ->label('Test Mode')
                            ->boolean()
                            ->trueColor('warning')
                            ->falseColor('gray'),
                    ])->carded(), // @phpstan-ignore method.notFound
                ])->glow(), // @phpstan-ignore method.notFound
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'created' => 'Created',
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                        'captured' => 'Captured',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refund_pending' => 'Refund Pending',
                        'refunding' => 'Refunding',
                        'partially_paid' => 'Partially Paid',
                        'chargeback' => 'Chargeback',
                    ]),
                Filter::make('is_test')
                    ->label('Test Mode')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_test', true)),
                Filter::make('high_value')
                    ->label('High Value (≥ 5,000)')
                    ->query(fn (Builder $query): Builder => $query->whereRaw("(purchase->>'amount')::int >= ?", [500000])),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_on', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-chip.polling_interval', '45s'));
    }

    private static function formatAmount(?int $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $precision = (int) config('filament-chip.tables.amount_precision', 2);
        $value = $amount / 100;
        $formatted = number_format($value, $precision, '.', ',');

        return mb_trim(sprintf('%s%s', $currency !== null && $currency !== '' && $currency !== '0' ? mb_strtoupper($currency).' ' : '', $formatted));
    }
}
