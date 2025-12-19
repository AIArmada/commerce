<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\InvoiceResource\Schemas;

use AIArmada\Chip\Models\Purchase;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

final class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice Summary')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('reference')
                                ->label('Invoice #')
                                ->copyable()
                                ->weight(FontWeight::SemiBold)
                                ->icon(Heroicon::OutlinedTag),

                            TextEntry::make('formatted_total')
                                ->label('Total Amount')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),

                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (Purchase $record): string => $record->statusColor())
                                ->formatStateUsing(fn (Purchase $record): string => $record->statusBadge()),

                            TextEntry::make('created_on')
                                ->label('Invoice Date')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedCalendar),
                        ]),
                ]),

            Section::make('Customer Details')
                ->icon(Heroicon::OutlinedUserCircle)
                ->schema([
                    Fieldset::make('Contact Information')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    TextEntry::make('client.full_name')
                                        ->label('Name')
                                        ->icon(Heroicon::OutlinedUser)
                                        ->placeholder('—'),

                                    TextEntry::make('client.email')
                                        ->label('Email')
                                        ->copyable()
                                        ->icon(Heroicon::OutlinedEnvelope)
                                        ->placeholder('—'),

                                    TextEntry::make('client.phone')
                                        ->label('Phone')
                                        ->icon(Heroicon::OutlinedPhone)
                                        ->placeholder('—'),
                                ]),
                        ]),

                    Fieldset::make('Billing Address')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    TextEntry::make('client.street_address')
                                        ->label('Address')
                                        ->placeholder('—'),

                                    TextEntry::make('client.city')
                                        ->label('City')
                                        ->placeholder('—'),

                                    TextEntry::make('client.country')
                                        ->label('Country')
                                        ->badge()
                                        ->placeholder('—'),
                                ]),
                        ])
                        ->visible(fn (Purchase $record): bool => Arr::hasAny($record->client ?? [], ['street_address', 'city', 'country'])),
                ]),

            Section::make('Amount Details')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('purchase.subtotal')
                                ->label('Subtotal')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR')
                                ))
                                ->placeholder('—'),

                            TextEntry::make('purchase.total_discount_override')
                                ->label('Discount')
                                ->formatStateUsing(
                                    fn (?int $state, Purchase $record): ?string => $state !== null
                                    ? '-' . self::formatAmount($state, Arr::get($record->purchase, 'currency', 'MYR'))
                                    : null
                                )
                                ->color('success')
                                ->placeholder('—'),

                            TextEntry::make('purchase.total_tax_override')
                                ->label('Tax')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR')
                                ))
                                ->placeholder('—'),

                            TextEntry::make('purchase.total')
                                ->label('Total')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR')
                                ))
                                ->weight(FontWeight::Bold),
                        ]),
                ]),

            Section::make('Line Items')
                ->icon(Heroicon::OutlinedListBullet)
                ->schema([
                    RepeatableEntry::make('purchase.products')
                        ->label('Products')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Product')
                                ->weight(FontWeight::Medium),

                            TextEntry::make('quantity')
                                ->label('Qty')
                                ->formatStateUsing(fn ($state): string => is_numeric($state) ? (string) (int) (float) $state : (string) $state),

                            TextEntry::make('price')
                                ->label('Unit Price')
                                ->formatStateUsing(fn (?int $state): ?string => self::formatAmount($state, 'MYR')),

                            TextEntry::make('category')
                                ->label('Category')
                                ->badge()
                                ->color('gray')
                                ->placeholder('—'),
                        ])
                        ->grid(1)
                        ->visible(fn (Purchase $record): bool => filled($record->purchase['products'] ?? [])),
                ])
                ->collapsible(),

            Section::make('Payment Information')
                ->icon(Heroicon::OutlinedCreditCard)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('payment.payment_method_name')
                                ->label('Payment Method')
                                ->badge()
                                ->color('primary')
                                ->placeholder('—'),

                            TextEntry::make('payment.card_brand')
                                ->label('Card Brand')
                                ->badge()
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? ucfirst($state) : null)
                                ->placeholder('—'),

                            TextEntry::make('payment.card_last_4')
                                ->label('Card')
                                ->formatStateUsing(fn (?string $state): ?string => $state !== null ? '•••• ' . $state : null)
                                ->placeholder('—'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('paid_on')
                                ->label('Paid At')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('Not paid'),

                            TextEntry::make('payment.fee_amount')
                                ->label('Processing Fee')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR')
                                ))
                                ->color('danger')
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (Purchase $record): bool => $record->status === 'paid')
                ->collapsible(),

            Section::make('Checkout')
                ->icon(Heroicon::OutlinedShoppingCart)
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('checkout_url')
                                ->label('Checkout URL')
                                ->copyable()
                                ->url(fn (Purchase $record): ?string => $record->checkout_url)
                                ->openUrlInNewTab()
                                ->placeholder('—'),

                            TextEntry::make('due')
                                ->label('Due Date')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                        ]),
                ])
                ->visible(fn (Purchase $record): bool => $record->status !== 'paid' && ! empty($record->checkout_url))
                ->collapsible(),

            Section::make('Timestamps')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('created_on')
                                ->label('Created')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s')),

                            TextEntry::make('updated_on')
                                ->label('Updated')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),

                            TextEntry::make('viewed_on')
                                ->label('Viewed')
                                ->dateTime(config('filament-cashier-chip.tables.date_format', 'Y-m-d H:i:s'))
                                ->placeholder('Never'),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    private static function formatAmount(?int $amount, ?string $currency): ?string
    {
        if ($amount === null) {
            return null;
        }

        $currency = $currency ?? config('cashier-chip.currency', 'MYR');
        $precision = (int) config('filament-cashier-chip.tables.amount_precision', 2);
        $value = $amount / 100;

        return mb_strtoupper($currency) . ' ' . number_format($value, $precision, '.', ',');
    }
}
