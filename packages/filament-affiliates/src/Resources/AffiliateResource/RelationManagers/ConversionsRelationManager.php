<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Tables\AffiliateConversionsTable;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ConversionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conversions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_reference')
            ->columns([
                TextColumn::make('order_reference')
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
                    ->label('Status')
                    ->badge()
                    ->color(fn (ConversionStatus | string $state): string => AffiliateConversionsTable::statusColor($state))
                    ->formatStateUsing(fn (ConversionStatus | string $state): string => AffiliateConversionsTable::statusLabel($state))
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliateConversion $record): string => AffiliateConversionResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
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
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(ApprovedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, ApprovedConversion::class)),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(RejectedConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, RejectedConversion::class)),
                Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('primary')
                    ->visible(fn (AffiliateConversion $record): bool => ! $record->status->equals(PaidConversion::class))
                    ->requiresConfirmation()
                    ->action(fn (AffiliateConversion $record): bool => AffiliateConversionsTable::updateStatus($record, PaidConversion::class)),
            ])
            ->emptyStateHeading('No conversions yet');
    }
}
