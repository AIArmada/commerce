<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardType;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Akaunting\Money\Money;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class GiftCardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Gift Card Details')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('code')
                                ->label('Code')
                                ->copyable()
                                ->weight('bold')
                                ->size('lg'),

                            TextEntry::make('type')
                                ->label('Type')
                                ->badge()
                                ->color(static fn (GiftCardType|string $state): string => $state instanceof GiftCardType ? $state->color() : GiftCardType::from($state)->color())
                                ->formatStateUsing(static fn (GiftCardType|string $state): string => $state instanceof GiftCardType ? $state->label() : GiftCardType::from($state)->label()),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(static fn (GiftCardStatus|string $state): string => match ($state instanceof GiftCardStatus ? $state : GiftCardStatus::from($state)) {
                                    GiftCardStatus::Active => 'success',
                                    GiftCardStatus::Inactive => 'warning',
                                    GiftCardStatus::Suspended => 'danger',
                                    GiftCardStatus::Expired => 'gray',
                                    GiftCardStatus::Exhausted => 'info',
                                    GiftCardStatus::Cancelled => 'danger',
                                })
                                ->formatStateUsing(static fn (GiftCardStatus|string $state): string => $state instanceof GiftCardStatus ? $state->label() : GiftCardStatus::from($state)->label()),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextEntry::make('initial_balance')
                                ->label('Initial Balance')
                                ->formatStateUsing(static fn (int $state, GiftCard $record): string => (string) Money::{$record->currency}($state)),

                            TextEntry::make('current_balance')
                                ->label('Current Balance')
                                ->formatStateUsing(static fn (int $state, GiftCard $record): string => (string) Money::{$record->currency}($state))
                                ->color(static fn (int $state): string => $state > 0 ? 'success' : 'danger'),

                            TextEntry::make('balance_utilization')
                                ->label('Utilization')
                                ->formatStateUsing(static fn (float $state): string => number_format($state, 1).'%')
                                ->badge()
                                ->color(static fn (float $state): string => match (true) {
                                    $state >= 75 => 'success',
                                    $state >= 50 => 'warning',
                                    default => 'info',
                                }),
                        ]),

                    IconEntry::make('has_pin')
                        ->label('PIN Protected')
                        ->boolean()
                        ->state(fn (GiftCard $record): bool => $record->pin !== null),
                ]),

            Section::make('Timeline')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),

                            TextEntry::make('activated_at')
                                ->label('Activated')
                                ->dateTime()
                                ->placeholder('Not activated'),

                            TextEntry::make('last_used_at')
                                ->label('Last Used')
                                ->dateTime()
                                ->placeholder('Never used'),

                            TextEntry::make('expires_at')
                                ->label('Expires')
                                ->dateTime()
                                ->placeholder('No expiration'),
                        ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    KeyValueEntry::make('metadata')
                        ->label('Metadata'),
                ])
                ->collapsed()
                ->hidden(fn (GiftCard $record): bool => empty($record->metadata)),
        ]);
    }
}
