<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Schemas;

use AIArmada\FilamentChip\Models\ChipPurchase;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

final class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Purchase Summary')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('reference')
                                ->label('Reference')
                                ->icon(Heroicon::OutlinedTag)
                                ->copyable()
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('formatted_total')
                                ->label('Grand Total')
                                ->badge()
                                ->color('primary')
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn (ChipPurchase $record): string => $record->statusColor())
                                ->formatStateUsing(fn (ChipPurchase $record): string => $record->statusBadge()),
                        ]),
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('created_on')
                                ->label('Created')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->icon(Heroicon::OutlinedClock),
                            TextEntry::make('due')
                                ->label('Due')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->placeholder('—')
                                ->icon(Heroicon::OutlinedCalendarDays),
                            TextEntry::make('viewed_on')
                                ->label('Viewed')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->placeholder('—')
                                ->icon(Heroicon::OutlinedEye),
                        ]),
                ]),

            Section::make('Client')
                ->schema([
                    Fieldset::make('Billing')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('client.full_name')
                                ->label('Name')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->placeholder('—'),
                            TextEntry::make('client.email')
                                ->label('Email')
                                ->icon(Heroicon::OutlinedEnvelope)
                                ->copyable()
                                ->placeholder('—'),
                            TextEntry::make('client.phone')
                                ->label('Phone')
                                ->icon(Heroicon::OutlinedPhone)
                                ->placeholder('—'),
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
                    Fieldset::make('Shipping')->inlineLabelled() // @phpstan-ignore method.notFound
                        ->schema([
                            TextEntry::make('client.shipping_street_address')
                                ->label('Address')
                                ->placeholder('—'),
                            TextEntry::make('client.shipping_city')
                                ->label('City')
                                ->placeholder('—'),
                            TextEntry::make('client.shipping_country')
                                ->label('Country')
                                ->badge()
                                ->placeholder('—'),
                        ])
                        ->visible(fn (ChipPurchase $record): bool => Arr::hasAny($record->client ?? [], [
                            'shipping_street_address',
                            'shipping_city',
                            'shipping_country',
                        ])),
                ]),

            Section::make('Amounts')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('purchase.subtotal.amount')
                                ->label('Subtotal')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'subtotal.currency', Arr::get($record->purchase, 'currency')),
                                ))
                                ->icon(Heroicon::OutlinedBanknotes),
                            TextEntry::make('purchase.discount.amount')
                                ->label('Discount')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'discount.currency', Arr::get($record->purchase, 'currency')),
                                ))
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('purchase.taxes.amount')
                                ->label('Taxes')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'taxes.currency', Arr::get($record->purchase, 'currency')),
                                ))
                                ->placeholder('—')
                                ->icon(Heroicon::OutlinedSparkles),
                            TextEntry::make('purchase.shipping.amount')
                                ->label('Shipping')
                                ->formatStateUsing(fn (?int $state, ChipPurchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'shipping.currency', Arr::get($record->purchase, 'currency')),
                                ))
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('purchase.line_items')
                        ->label('Items')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('quantity')
                                ->label('Quantity'),
                            TextEntry::make('price.amount')
                                ->label('Unit Price')
                                ->formatStateUsing(fn (?int $state, array $entry): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($entry, 'price.currency'),
                                )),
                            TextEntry::make('subtotal.amount')
                                ->label('Subtotal')
                                ->formatStateUsing(fn (?int $state, array $entry): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($entry, 'subtotal.currency', Arr::get($entry, 'price.currency')),
                                )),
                            TextEntry::make('metadata')
                                ->label('Metadata')
                                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                                ->visible(fn (array $entry): bool => filled($entry['metadata'] ?? []))
                                ->columnSpanFull(),
                        ])
                        ->grid(1)
                        ->visible(fn (ChipPurchase $record): bool => filled($record->purchase['line_items'] ?? [])),
                ])
                ->collapsible(),

            Section::make('Status Timeline')
                ->schema([
                    RepeatableEntry::make('timeline')
                        ->label('Status Changes')
                        ->schema([
                            TextEntry::make('translated')
                                ->label('Status')
                                ->badge()
                                ->color(fn (string $state): string => match (mb_strtolower($state)) {
                                    'paid', 'completed', 'captured' => 'success',
                                    'processing', 'partially paid', 'refund pending' => 'warning',
                                    'failed', 'cancelled', 'chargeback' => 'danger',
                                    default => 'secondary',
                                }),
                            TextEntry::make('timestamp')
                                ->label('When')
                                ->dateTime(config('filament-chip.tables.created_on_format', 'Y-m-d H:i:s'))
                                ->placeholder('—'),
                        ])
                        ->grid(1)
                        ->visible(fn (ChipPurchase $record): bool => filled($record->timeline)),
                ])
                ->collapsible(),

            Section::make('Raw Payloads')
                ->schema([
                    TextEntry::make('purchase')
                        ->label('Purchase JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (ChipPurchase $record): bool => filled($record->purchase))
                        ->columnSpanFull(),
                    TextEntry::make('payment')
                        ->label('Payment JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (ChipPurchase $record): bool => filled($record->payment ?? []))
                        ->columnSpanFull(),
                    TextEntry::make('transaction_data')
                        ->label('Transaction Data JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (ChipPurchase $record): bool => filled($record->transaction_data ?? []))
                        ->columnSpanFull(),
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

        $precision = (int) config('filament-chip.tables.amount_precision', 2);
        $value = $amount / 100;
        $formatted = number_format($value, $precision, '.', ',');

        return mb_trim(sprintf('%s%s', $currency !== null && $currency !== '' && $currency !== '0' ? mb_strtoupper($currency).' ' : '', $formatted));
    }
}
