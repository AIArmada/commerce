<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class AffiliateConversionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate_code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('external_reference')
                    ->label('Reference')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (AffiliateConversion $record): string => sprintf(
                        '%s %.2f',
                        $record->commission_currency,
                        $record->commission_minor / 100
                    ))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ConversionStatus | string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (ConversionStatus | string $state): string => self::statusLabel($state))
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ConversionStatus::options()),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliateConversion $record): string => AffiliateConversionResource::getUrl('view', ['record' => $record])),
                Action::make('cart')
                    ->label('Cart')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->visible(fn () => app(CartBridge::class)->isAvailable())
                    ->url(fn (AffiliateConversion $record): ?string => app(CartBridge::class)->resolveUrl(
                        $record->cart_identifier,
                        $record->cart_instance
                    ))
                    ->openUrlInNewTab(),
                Action::make('voucher')
                    ->label('Voucher')
                    ->icon(Heroicon::OutlinedTicket)
                    ->visible(fn (AffiliateConversion $record): bool => $record->voucher_code !== null && app(VoucherBridge::class)->isAvailable())
                    ->url(fn (AffiliateConversion $record): ?string => app(VoucherBridge::class)->resolveUrl($record->voucher_code))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(ApprovedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, ApprovedConversion::class)),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(RejectedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, RejectedConversion::class)),
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->color('primary')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(PaidConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, PaidConversion::class)),
                Action::make('reset_pending')
                    ->label('Reset to Pending')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(PendingConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, PendingConversion::class)),
            ])
            ->bulkActions([]);
    }

    public static function statusColor(ConversionStatus | string $state): string
    {
        return ConversionStatus::colorFor($state);
    }

    public static function statusLabel(ConversionStatus | string $state): string
    {
        return ConversionStatus::labelFor($state);
    }

    public static function updateStatus(AffiliateConversion $record, ConversionStatus | string $status): bool
    {
        $statusClass = ConversionStatus::resolveStateClassFor($status, $record);

        $record->status = new $statusClass($record);
        $record->approved_at = in_array($statusClass, [ApprovedConversion::class, PaidConversion::class], true)
            ? ($record->approved_at ?? now())
            : null;

        return $record->save();
    }
}
