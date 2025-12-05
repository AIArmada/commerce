<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\RelationManagers;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardTransactionType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Models\GiftCardTransaction;
use Akaunting\Money\Money;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transaction History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(static fn (GiftCardTransactionType|string $state): string => match ($state instanceof GiftCardTransactionType ? $state : GiftCardTransactionType::from($state)) {
                        GiftCardTransactionType::Issue => 'info',
                        GiftCardTransactionType::Activate => 'success',
                        GiftCardTransactionType::TopUp => 'success',
                        GiftCardTransactionType::Redeem => 'warning',
                        GiftCardTransactionType::Refund => 'primary',
                        GiftCardTransactionType::Transfer => 'secondary',
                        GiftCardTransactionType::Expire => 'danger',
                        GiftCardTransactionType::Merge => 'info',
                        GiftCardTransactionType::Adjustment => 'gray',
                    })
                    ->formatStateUsing(static fn (GiftCardTransactionType|string $state): string => $state instanceof GiftCardTransactionType ? $state->label() : GiftCardTransactionType::from($state)->label()),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(static function (int $state, GiftCardTransaction $record): string {
                        /** @var GiftCard $giftCard */
                        $giftCard = $record->giftCard;
                        $formatted = (string) Money::{$giftCard->currency}(abs($state));

                        return $state >= 0 ? '+'.$formatted : '-'.$formatted;
                    })
                    ->color(static fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->alignEnd(),

                TextColumn::make('balance_before')
                    ->label('Before')
                    ->formatStateUsing(static function (int $state, GiftCardTransaction $record): string {
                        /** @var GiftCard $giftCard */
                        $giftCard = $record->giftCard;

                        return (string) Money::{$giftCard->currency}($state);
                    })
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('balance_after')
                    ->label('After')
                    ->formatStateUsing(static function (int $state, GiftCardTransaction $record): string {
                        /** @var GiftCard $giftCard */
                        $giftCard = $record->giftCard;

                        return (string) Money::{$giftCard->currency}($state);
                    })
                    ->alignEnd(),

                TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->striped();
    }
}
