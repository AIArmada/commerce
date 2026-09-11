<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Schemas;

use AIArmada\Addressing\Models\Address;
use AIArmada\Orders\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class OrderInfolist
{
    /**
     * @return array<int, Section>
     */
    public static function schema(): array
    {
        return [
            Section::make('Order Details')
                ->schema([
                    TextEntry::make('order_number')
                        ->label('Order Number')
                        ->copyable()
                        ->weight('bold'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => $state?->label() ?? 'Unknown')
                        ->color(fn ($state) => match ($state?->color() ?? 'gray') {
                            'success' => 'success',
                            'warning' => 'warning',
                            'danger' => 'danger',
                            'info' => 'info',
                            'primary' => 'primary',
                            default => 'gray',
                        }),

                    TextEntry::make('created_at')
                        ->label('Order Date')
                        ->dateTime('d M Y H:i'),

                    TextEntry::make('paid_at')
                        ->label('Paid At')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Not paid'),
                ])
                ->columns(4),

            Section::make('Customer')
                ->schema([
                    TextEntry::make('customer.name')
                        ->label('Name')
                        ->placeholder('Guest'),

                    TextEntry::make('customer.email')
                        ->label('Email')
                        ->placeholder('-'),
                ])
                ->columns(2),

            Section::make('Addresses')
                ->schema([
                    TextEntry::make('billing_address')
                        ->label('Billing Address')
                        ->getStateUsing(fn (Order $record): ?HtmlString => static::formatAddress($record->primaryAddress('billing')))
                        ->placeholder('Not provided')
                        ->html(),

                    TextEntry::make('shipping_address')
                        ->label('Shipping Address')
                        ->getStateUsing(fn (Order $record): ?HtmlString => static::formatAddress($record->primaryAddress('shipping')))
                        ->placeholder('Not provided')
                        ->html(),
                ])
                ->columns(2),

            Section::make('Order Totals')
                ->schema([
                    TextEntry::make('subtotal')
                        ->label('Subtotal')
                        ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                    TextEntry::make('discount_total')
                        ->label('Discount')
                        ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                        ->visible(fn ($record) => $record->discount_total > 0),

                    TextEntry::make('shipping_total')
                        ->label('Shipping')
                        ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                    TextEntry::make('tax_total')
                        ->label('Tax')
                        ->money(fn (Order $record): string => $record->currency, divideBy: 100),

                    TextEntry::make('grand_total')
                        ->label('Grand Total')
                        ->money(fn (Order $record): string => $record->currency, divideBy: 100)
                        ->weight('bold')
                        ->size('lg'),
                ])
                ->columns(5),

            Section::make('Notes')
                ->schema([
                    TextEntry::make('notes')
                        ->label('Customer Notes')
                        ->placeholder('No notes'),

                    TextEntry::make('internal_notes')
                        ->label('Internal Notes')
                        ->placeholder('No internal notes'),
                ])
                ->columns(2)
                ->collapsible(),
        ];
    }

    private static function formatAddress(?Address $address): ?HtmlString
    {
        if ($address === null) {
            return null;
        }

        $metadata = $address->metadata;
        $contact = is_array($metadata)
            && is_array($metadata[Order::ADDRESS_CONTACT_METADATA_KEY] ?? null)
            ? $metadata[Order::ADDRESS_CONTACT_METADATA_KEY]
            : [];
        $hasText = static fn (mixed $value): bool => is_string($value) && $value !== '';
        $name = mb_trim(implode(' ', array_filter([
            $contact['first_name'] ?? null,
            $contact['last_name'] ?? null,
        ], $hasText)));
        $locality = implode(', ', array_filter([
            $address->city,
            $address->state,
        ], $hasText));
        $location = mb_trim(implode(' ', array_filter([
            $locality,
            $address->postcode,
        ], $hasText)));

        $lines = array_filter([
            $name,
            is_string($contact['company'] ?? null) ? $contact['company'] : null,
            $address->line1,
            $address->line2,
            $location,
            $address->country_code ?? $address->country,
            is_string($contact['phone'] ?? null) ? $contact['phone'] : null,
        ], $hasText);

        if ($lines === []) {
            return null;
        }

        return new HtmlString(nl2br(e(implode("\n", $lines))));
    }
}
