<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\BankAccountResource\Schemas;

use AIArmada\Chip\Models\BankAccount;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class BankAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Information')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Account ID')
                                ->copyable()
                                ->weight(FontWeight::Bold),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(fn (BankAccount $record): string => $record->statusLabel())
                                ->color(fn (BankAccount $record): string => $record->statusColor()),

                            TextEntry::make('reference')
                                ->label('Reference')
                                ->placeholder('Not set'),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Account Holder')
                                ->weight(FontWeight::Medium),

                            TextEntry::make('account_number')
                                ->label('Account Number')
                                ->copyable(),

                            TextEntry::make('bank_code')
                                ->label('Bank Code')
                                ->badge()
                                ->color('gray'),
                        ]),
                ]),

            Section::make('Capabilities')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            IconEntry::make('is_debiting_account')
                                ->label('Debiting Enabled')
                                ->boolean()
                                ->trueIcon(Heroicon::OutlinedCheckCircle)
                                ->falseIcon(Heroicon::OutlinedXCircle)
                                ->trueColor('success')
                                ->falseColor('danger'),

                            IconEntry::make('is_crediting_account')
                                ->label('Crediting Enabled')
                                ->boolean()
                                ->trueIcon(Heroicon::OutlinedCheckCircle)
                                ->falseIcon(Heroicon::OutlinedXCircle)
                                ->trueColor('success')
                                ->falseColor('danger'),

                            TextEntry::make('group_id')
                                ->label('Group ID')
                                ->placeholder('Not assigned'),
                        ]),
                ]),

            Section::make('Status Details')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->placeholder('No rejection reason')
                        ->columnSpanFull()
                        ->visible(fn (BankAccount $record): bool => $record->status === 'rejected'),

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
