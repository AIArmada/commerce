<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Schemas;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Support\FormatsSubscriptionStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class SubscriptionInfolist
{
    use FormatsSubscriptionStatus;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Subscription Overview')
                ->icon(Heroicon::OutlinedCreditCard)
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('type')
                                ->label('Subscription Type')
                                ->weight(FontWeight::SemiBold)
                                ->icon(Heroicon::OutlinedTag),

                            TextEntry::make('chip_id')
                                ->label('Chip ID')
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('chip_status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (Subscription $record): string => self::getStatusColor($record->chip_status))
                                ->formatStateUsing(fn (Subscription $record): string => self::formatStatus($record->chip_status)),

                            TextEntry::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Plan Details')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('chip_price')
                                ->label('Price ID')
                                ->badge()
                                ->color('primary')
                                ->placeholder('Multiple Prices'),

                            TextEntry::make('billing_interval')
                                ->label('Billing Interval')
                                ->formatStateUsing(fn (?string $state, Subscription $record): string => self::formatInterval($state, $record->billing_interval_count)),

                            TextEntry::make('recurring_token')
                                ->label('Payment Method')
                                ->copyable()
                                ->placeholder('Default'),
                        ]),
                ]),

            Section::make('Customer')
                ->icon(Heroicon::OutlinedUserCircle)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('owner.name')
                                ->label('Name')
                                ->icon(Heroicon::OutlinedUser)
                                ->placeholder('—'),

                            TextEntry::make('owner.email')
                                ->label('Email')
                                ->icon(Heroicon::OutlinedEnvelope)
                                ->copyable()
                                ->placeholder('—'),

                            TextEntry::make('owner.chip_id')
                                ->label('Chip Customer ID')
                                ->copyable()
                                ->placeholder('Not linked'),
                        ]),
                ]),

            Section::make('Billing Schedule')
                ->icon(Heroicon::OutlinedCalendar)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('trial_ends_at')
                                ->label('Trial Ends')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock)
                                ->placeholder('No Trial')
                                ->color(fn (Subscription $record): ?string => $record->onTrial() ? 'warning' : null),

                            TextEntry::make('next_billing_at')
                                ->label('Next Billing')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedCalendarDays)
                                ->placeholder('—'),

                            TextEntry::make('ends_at')
                                ->label('Ends At')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedXCircle)
                                ->placeholder('Active')
                                ->color(fn (Subscription $record): ?string => $record->onGracePeriod() ? 'warning' : ($record->canceled() ? 'danger' : null)),
                        ]),
                ]),

            Section::make('Discount')
                ->icon(Heroicon::OutlinedGift)
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('coupon_id')
                                ->label('Coupon Code')
                                ->badge()
                                ->color('success')
                                ->placeholder('No Discount'),

                            TextEntry::make('coupon_discount')
                                ->label('Discount Amount')
                                ->formatStateUsing(fn (?int $state): ?string => $state !== null ? self::formatAmount($state) : null)
                                ->placeholder('—'),

                            TextEntry::make('coupon_duration')
                                ->label('Duration')
                                ->badge()
                                ->color('gray')
                                ->placeholder('—'),

                            TextEntry::make('coupon_applied_at')
                                ->label('Applied At')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (Subscription $record): bool => $record->hasDiscount())
                ->collapsible(),

            Section::make('Timestamps')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
