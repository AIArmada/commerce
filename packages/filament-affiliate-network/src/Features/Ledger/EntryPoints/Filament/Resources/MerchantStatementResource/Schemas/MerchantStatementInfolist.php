<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource\Schemas;

use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\MerchantStatement;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MerchantStatementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Statement')
                    ->schema([
                        TextEntry::make('statement_number')
                            ->label('Statement number')
                            ->copyable(),

                        TextEntry::make('site.name')
                            ->label('Site'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (SettlementStatus | string $state): string => $state instanceof SettlementStatus ? $state->color() : (SettlementStatus::tryFrom((string) $state)?->color() ?? 'gray')),

                        TextEntry::make('currency')
                            ->label('Currency')
                            ->badge(),

                        TextEntry::make('period_start')
                            ->label('Period start')
                            ->dateTime(),

                        TextEntry::make('period_end')
                            ->label('Period end')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Amounts')
                    ->schema([
                        TextEntry::make('leg_count')
                            ->label('Legs')
                            ->numeric(),

                        TextEntry::make('commission_minor')
                            ->label('Commission')
                            ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->currency)),

                        TextEntry::make('fee_minor')
                            ->label('Fee')
                            ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->fee_minor, $record->currency)),

                        TextEntry::make('payout_minor')
                            ->label('Payout')
                            ->formatStateUsing(fn (MerchantStatement $record): string => MoneyFormatter::formatMinor($record->payout_minor, $record->currency)),
                    ])
                    ->columns(4),

                Section::make('Settlement')
                    ->schema([
                        TextEntry::make('issued_at')
                            ->label('Issued at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('due_at')
                            ->label('Due at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('paid_at')
                            ->label('Paid at')
                            ->dateTime()
                            ->placeholder('—'),

                        TextEntry::make('payment_reference')
                            ->label('Payment reference')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
            ]);
    }
}
