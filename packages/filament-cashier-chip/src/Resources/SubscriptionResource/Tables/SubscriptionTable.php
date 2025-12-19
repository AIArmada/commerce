<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Tables;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\FormatsSubscriptionStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class SubscriptionTable
{
    use FormatsSubscriptionStatus;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Subscription Type')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('chip_price')
                    ->label('Price')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder('Multiple'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('chip_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Subscription $record): string => self::getStatusColor($record->chip_status))
                    ->formatStateUsing(fn (string $state): string => self::formatStatus($state)),

                IconColumn::make('on_trial')
                    ->label('Trial')
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Subscription $record): bool => $record->onTrial()),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_billing_at')
                    ->label('Next Billing')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Subscription $record): string => $record->onGracePeriod() ? 'warning' : 'danger'),

                TextColumn::make('billing_interval')
                    ->label('Interval')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state, Subscription $record): string => self::formatInterval($state, $record->billing_interval_count)),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('chip_status')
                    ->label('Status')
                    ->options([
                        Subscription::STATUS_ACTIVE => 'Active',
                        Subscription::STATUS_TRIALING => 'Trialing',
                        Subscription::STATUS_CANCELED => 'Canceled',
                        Subscription::STATUS_PAST_DUE => 'Past Due',
                        Subscription::STATUS_PAUSED => 'Paused',
                        Subscription::STATUS_INCOMPLETE => 'Incomplete',
                        Subscription::STATUS_UNPAID => 'Unpaid',
                    ]),

                TernaryFilter::make('on_trial')
                    ->label('Trial Status')
                    ->placeholder('All')
                    ->trueLabel('On Trial')
                    ->falseLabel('Not On Trial')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now());
                        }),
                    ),

                TernaryFilter::make('canceled')
                    ->label('Canceled')
                    ->placeholder('All')
                    ->trueLabel('Canceled')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('ends_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('ends_at'),
                    ),

                Filter::make('on_grace_period')
                    ->label('On Grace Period')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('ends_at')->where('ends_at', '>', now())),

                Filter::make('past_due')
                    ->label('Past Due')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('chip_status', Subscription::STATUS_PAST_DUE)),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                ViewAction::make()
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll(config('filament-cashier-chip.tables.polling_interval', '45s'));
    }
}
