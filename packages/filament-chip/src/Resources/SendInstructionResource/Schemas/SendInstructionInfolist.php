<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class SendInstructionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payout Information')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Instruction ID')
                                ->copyable()
                                ->weight(FontWeight::Bold),

                            TextEntry::make('reference')
                                ->label('Reference')
                                ->copyable(),

                            TextEntry::make('state')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn ($record): string => $record->stateLabel)
                                ->color(fn ($record): string => $record->stateColor()),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('amount')
                                ->label('Amount')
                                ->formatStateUsing(fn ($state): string => 'RM ' . number_format((float) $state, 2))
                                ->size('lg')
                                ->weight(FontWeight::Bold),

                            TextEntry::make('email')
                                ->label('Notification Email')
                                ->icon(Heroicon::OutlinedEnvelope)
                                ->copyable(),
                        ]),

                    TextEntry::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                ]),

            Section::make('Recipient Details')
                ->icon(Heroicon::OutlinedUser)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('bankAccount.name')
                                ->label('Account Holder')
                                ->placeholder('Not available'),

                            TextEntry::make('bankAccount.account_number')
                                ->label('Account Number')
                                ->copyable()
                                ->placeholder('Not available'),

                            TextEntry::make('bankAccount.bank_code')
                                ->label('Bank Code')
                                ->placeholder('Not available'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('bankAccount.status')
                                ->label('Account Status')
                                ->badge()
                                ->formatStateUsing(fn ($record): string => $record->bankAccount?->statusLabel() ?? 'Unknown')
                                ->color(fn ($record): string => $record->bankAccount?->statusColor() ?? 'gray'),

                            TextEntry::make('bankAccount.reference')
                                ->label('Account Reference')
                                ->placeholder('Not available'),
                        ]),
                ]),

            Section::make('Metadata')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('slug')
                                ->label('Slug')
                                ->copyable()
                                ->placeholder('Not available'),

                            TextEntry::make('receipt_url')
                                ->label('Receipt URL')
                                ->url(fn ($state): ?string => $state)
                                ->openUrlInNewTab()
                                ->placeholder('Not available'),

                            TextEntry::make('bank_account_id')
                                ->label('Bank Account ID'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(),
                        ]),
                ]),
        ]);
    }
}
