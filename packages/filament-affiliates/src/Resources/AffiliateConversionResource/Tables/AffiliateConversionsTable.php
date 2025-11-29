<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\AffiliateConversion;
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

                TextColumn::make('order_reference')
                    ->label('Order / Ref')
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
                    ->color(fn (ConversionStatus|string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (ConversionStatus|string $state): string => self::statusLabel($state))
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'qualified' => 'Qualified',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'paid' => 'Paid',
                    ]),
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
                    ->visible(fn (AffiliateConversion $record): bool => self::statusEnum($record->status) !== ConversionStatus::Approved)
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, ConversionStatus::Approved)),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (AffiliateConversion $record): bool => self::statusEnum($record->status) !== ConversionStatus::Rejected)
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, ConversionStatus::Rejected)),
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->color('primary')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn (AffiliateConversion $record): bool => self::statusEnum($record->status) !== ConversionStatus::Paid)
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, ConversionStatus::Paid)),
                Action::make('reset_pending')
                    ->label('Reset to Pending')
                    ->color('gray')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (AffiliateConversion $record): bool => self::statusEnum($record->status) !== ConversionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => self::updateStatus($record, ConversionStatus::Pending)),
            ])
            ->bulkActions([]);
    }

    public static function statusColor(ConversionStatus|string $state): string
    {
        return match (self::statusEnum($state)) {
            ConversionStatus::Pending => 'warning',
            ConversionStatus::Qualified => 'info',
            ConversionStatus::Approved => 'success',
            ConversionStatus::Paid => 'primary',
            ConversionStatus::Rejected => 'danger',
        };
    }

    public static function statusLabel(ConversionStatus|string $state): string
    {
        return self::statusEnum($state)->label();
    }

    public static function statusEnum(ConversionStatus|string $state): ConversionStatus
    {
        return $state instanceof ConversionStatus ? $state : ConversionStatus::from((string) $state);
    }

    public static function updateStatus(AffiliateConversion $record, ConversionStatus $status): bool
    {
        $record->status = $status;
        $record->approved_at = in_array($status, [ConversionStatus::Approved, ConversionStatus::Paid], true)
            ? ($record->approved_at ?? now())
            : null;

        return $record->save();
    }
}
