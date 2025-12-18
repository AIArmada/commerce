<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\InvoiceResource\Tables;

use AIArmada\Chip\Models\Purchase;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InvoiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('created_on', 'desc'))
            ->columns([
                TextColumn::make('reference')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('client.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('client.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),

                TextColumn::make('formatted_total')
                    ->label('Amount')
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Purchase $record): string => $record->statusColor())
                    ->formatStateUsing(fn (Purchase $record): string => $record->statusBadge()),

                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn (Purchase $record): bool => $record->status === 'paid'),

                TextColumn::make('created_on')
                    ->label('Date')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable(),

                TextColumn::make('due')
                    ->label('Due Date')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_test')
                    ->label('Test')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'created' => 'Created',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'captured' => 'Captured',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refund_pending' => 'Refund Pending',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                    ]),

                Filter::make('paid')
                    ->label('Paid Only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', 'paid')),

                Filter::make('unpaid')
                    ->label('Unpaid Only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['created', 'pending', 'pending_execute'])),

                Filter::make('is_test')
                    ->label('Test Mode')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_test', true)),

                Filter::make('high_value')
                    ->label('High Value (≥ 1,000)')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $driver = $query->getConnection()->getDriverName();
                        $amount = 100000; // 1000 in cents

                        return match ($driver) {
                            'pgsql' => $query->whereRaw(
                                'COALESCE((purchase->>\'total\')::int, (purchase->\'total\'->>\'amount\')::int) >= ?',
                                [$amount]
                            ),
                            'mysql', 'mariadb' => $query->whereRaw(
                                'CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(purchase, "$.total")), JSON_UNQUOTE(JSON_EXTRACT(purchase, "$.total.amount"))) AS UNSIGNED) >= ?',
                                [$amount]
                            ),
                            default => $query->whereRaw(
                                "CAST(COALESCE(json_extract(purchase, '$.total'), json_extract(purchase, '$.total.amount')) AS INTEGER) >= ?",
                                [$amount]
                            ),
                        };
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Purchase $record): bool => $record->status === 'paid')
                    ->action(function (Purchase $record): void {
                        // PDF download logic would be handled here
                        // For now, we just show a notification
                    }),

                Action::make('view_checkout')
                    ->label('Checkout')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (Purchase $record): ?string => $record->checkout_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Purchase $record): bool => ! empty($record->checkout_url) && $record->status !== 'paid'),
            ])
            ->bulkActions([])
            ->defaultSort('created_on', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-cashier-chip.polling_interval', '45s'));
    }
}
