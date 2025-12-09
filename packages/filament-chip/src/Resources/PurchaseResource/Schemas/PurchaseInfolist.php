<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\PurchaseResource\Schemas;

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
                                ->color(fn (Purchase $record): string => $record->statusColor())
                                ->formatStateUsing(fn (Purchase $record): string => $record->statusBadge()),
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
                        ->visible(fn (Purchase $record): bool => Arr::hasAny($record->client ?? [], [
                            'shipping_street_address',
                            'shipping_city',
                            'shipping_country',
                        ])),
                ]),

            Section::make('Amounts')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            TextEntry::make('purchase.total')
                                ->label('Total')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR'),
                                ))
                                ->icon(Heroicon::OutlinedBanknotes)
                                ->weight(FontWeight::SemiBold),
                            TextEntry::make('purchase.total_discount_override')
                                ->label('Discount')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR'),
                                ))
                                ->color('success')
                                ->placeholder('—'),
                            TextEntry::make('purchase.total_tax_override')
                                ->label('Taxes')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR'),
                                ))
                                ->placeholder('—')
                                ->icon(Heroicon::OutlinedSparkles),
                            TextEntry::make('payment.fee_amount')
                                ->label('Fee')
                                ->formatStateUsing(fn (?int $state, Purchase $record): ?string => self::formatAmount(
                                    $state,
                                    Arr::get($record->purchase, 'currency', 'MYR'),
                                ))
                                ->color('danger')
                                ->placeholder('—'),
                        ]),
                ]),

            Section::make('Line Items')
                ->schema([
                    RepeatableEntry::make('purchase.products')
                        ->label('Products')
                        ->schema([
                            TextEntry::make('name')
                                ->label('Name')
                                ->weight(FontWeight::Medium),
                            TextEntry::make('quantity')
                                ->label('Qty')
                                ->formatStateUsing(fn ($state) => is_numeric($state) ? (int) (float) $state : $state),
                            TextEntry::make('price')
                                ->label('Unit Price')
                                ->formatStateUsing(fn (?int $state): ?string => self::formatAmount($state, 'MYR')),
                            TextEntry::make('category')
                                ->label('Category')
                                ->badge()
                                ->color('gray'),
                        ])
                        ->grid(1)
                        ->visible(fn (Purchase $record): bool => filled($record->purchase['products'] ?? [])),
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
                        ->visible(fn (Purchase $record): bool => filled($record->timeline)),
                ])
                ->collapsible(),

            Section::make('Raw Payloads')
                ->schema([
                    TextEntry::make('purchase')
                        ->label('Purchase JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (Purchase $record): bool => filled($record->purchase))
                        ->columnSpanFull(),
                    TextEntry::make('payment')
                        ->label('Payment JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (Purchase $record): bool => filled($record->payment ?? []))
                        ->columnSpanFull(),
                    TextEntry::make('transaction_data')
                        ->label('Transaction Data JSON')
                        ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->visible(fn (Purchase $record): bool => filled($record->transaction_data ?? []))
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

        return mb_trim(sprintf('%s%s', $currency !== null && $currency !== '' && $currency !== '0' ? mb_strtoupper($currency) . ' ' : '', $formatted));
    }
}
