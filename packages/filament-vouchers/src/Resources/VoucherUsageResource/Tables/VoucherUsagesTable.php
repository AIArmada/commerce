<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables;

use AIArmada\Vouchers\Models\VoucherUsage;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class VoucherUsagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('used_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['voucher', 'redeemedBy']))
            ->columns([
                TextColumn::make('redeemedBy.user.email')
                    ->label('User')
                    ->searchable()
                    ->wrap()
                    ->placeholder('N/A'),

                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->searchable()
                    ->url(fn (VoucherUsage $record): string => $record->voucher ? \AIArmada\FilamentVouchers\Resources\VoucherResource::getUrl('view', ['record' => $record->voucher]) : null)
                    ->placeholder('N/A'),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(static fn (string $state): string => match ($state) {
                        VoucherUsage::CHANNEL_MANUAL => 'warning',
                        VoucherUsage::CHANNEL_API => 'info',
                        default => 'success',
                    })
                    ->icon(static fn (string $state): Heroicon => match ($state) {
                        VoucherUsage::CHANNEL_MANUAL => Heroicon::OutlinedClipboardDocumentCheck,
                        VoucherUsage::CHANNEL_API => Heroicon::OutlinedCommandLine,
                        default => Heroicon::OutlinedBolt,
                    }),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->formatStateUsing(static function ($state, VoucherUsage $record): string {
                        $currency = mb_strtoupper((string) ($record->currency ?? config('filament-vouchers.default_currency', 'MYR')));

                        // Value is already stored as cents (integer)
                        return (string) Money::{$currency}((int) $state);
                    })
                    ->alignEnd(),

                TextColumn::make('redeemedBy.order_number')
                    ->label('Order Number')
                    ->toggleable()
                    ->formatStateUsing(fn ($state, VoucherUsage $record) => $record->redeemed_by_type === 'order' ? $state : null
                    )
                    ->url(function (VoucherUsage $record): ?string {
                        if ($record->redeemed_by_type !== 'order' || ! $record->redeemedBy) {
                            return null;
                        }

                        // Get the OrderResource class dynamically
                        $orderResourceClass = '\\App\\Filament\\Resources\\Orders\\OrderResource';

                        if (! class_exists($orderResourceClass)) {
                            return null;
                        }

                        return $orderResourceClass::getUrl('view', ['record' => $record->redeemedBy]);
                    })
                    ->placeholder('N/A'),

                TextColumn::make('used_at')
                    ->label('Redeemed At')
                    ->dateTime()
                    ->sortable(),

                IconColumn::make('metadata')
                    ->label('Notes?')
                    ->boolean()
                    ->tooltip('Voucher usage contains metadata or notes')
                    ->state(static fn (VoucherUsage $record): bool => ! empty($record->metadata) || ! empty($record->notes))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->label('Channel')
                    ->options([
                        VoucherUsage::CHANNEL_AUTOMATIC => 'Automatic',
                        VoucherUsage::CHANNEL_MANUAL => 'Manual',
                        VoucherUsage::CHANNEL_API => 'API',
                    ]),

                // Additional filters can be added once voucher usage gains soft deletes or status metadata.
            ])
            ->recordUrl(null);
    }
}
