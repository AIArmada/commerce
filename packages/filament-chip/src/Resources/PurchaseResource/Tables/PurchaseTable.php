<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Tables;

use AIArmada\Chip\Models\Purchase;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Component;
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
            ])
            ->columns([
                self::glowingSplit([
                    self::cardedStack([
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
                    ]),
                    self::softShadowPanel([
                        self::cardedStack([
                            TextColumn::make('formatted_total')
                                ->label('Grand Total')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),
                            TextColumn::make('purchase.subtotal.amount')
                                ->label('Subtotal')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    $record->purchase['subtotal']['currency'] ?? $record->purchase['currency'] ?? null,
                                ))
                                ->icon('heroicon-o-banknotes'),
                            TextColumn::make('purchase.taxes.amount')
                                ->label('Taxes')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    $record->purchase['taxes']['currency'] ?? $record->purchase['currency'] ?? null,
                                ))
                                ->icon('heroicon-o-sparkles')
                                ->placeholder('—'),
                        ]),
                    ]),
                    self::cardedStack([
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (Purchase $record): string => $record->statusColor())
                            ->formatStateUsing(fn (Purchase $record): string => $record->statusBadge()),
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
                    ]),
                ]),
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
                    ->query(function (Builder $query): Builder {
                        $driver = ConnectionDriver::name($query->getConnection());
                        $amount = 500000; // amounts are stored in cents

                        // Build DB-specific JSON extraction for purchase total with fallbacks
                        return match ($driver) {
                            'pgsql' => $query->whereRaw(
                                // Try purchase.total, then purchase.total.amount, then purchase.amount, then purchase.subtotal
                                'COALESCE((purchase->>\'total\')::int, (purchase->\'total\'->>\'amount\')::int, (purchase->>\'amount\')::int, (purchase->>\'subtotal\')::int) >= ?',
                                [$amount]
                            ),
                            'mysql', 'mariadb' => $query->whereRaw(
                                'CAST(COALESCE(
                                    JSON_UNQUOTE(JSON_EXTRACT(purchase, \"$.total\")),
                                    JSON_UNQUOTE(JSON_EXTRACT(purchase, \"$.total.amount\")),
                                    JSON_UNQUOTE(JSON_EXTRACT(purchase, \"$.amount\")),
                                    JSON_UNQUOTE(JSON_EXTRACT(purchase, \"$.subtotal\"))
                                ) AS UNSIGNED) >= ?',
                                [$amount]
                            ),
                            default => $query->whereRaw(
                                // SQLite
                                "CAST(COALESCE(
                                    json_extract(purchase, '$.total'),
                                    json_extract(purchase, '$.total.amount'),
                                    json_extract(purchase, '$.amount'),
                                    json_extract(purchase, '$.subtotal')
                                ) AS INTEGER) >= ?",
                                [$amount]
                            ),
                        };
                    }),
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

        return MoneyFormatter::formatMinor($amount, $currency ?? config('filament-chip.default_currency', 'MYR'), (int) config('filament-chip.tables.amount_precision', 2));
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function glowingSplit(array $components): Split
    {
        return Split::make($components)->extraAttributes([
            'class' => 'after:absolute after:inset-0 after:-z-10 after:rounded-2xl after:bg-gradient-to-r after:from-primary-500/20 after:to-transparent',
        ]);
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function softShadowPanel(array $components): Panel
    {
        return Panel::make($components)->extraAttributes([
            'class' => 'shadow-lg shadow-gray-200/40 ring-1 ring-black/5',
        ]);
    }

    /**
     * @param  array<int, Column|ColumnGroup|Component>  $components
     */
    private static function cardedStack(array $components): Stack
    {
        return Stack::make($components)->extraAttributes([
            'class' => 'rounded-2xl border border-white/60 bg-white/80 p-6 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5',
        ]);
    }
}
