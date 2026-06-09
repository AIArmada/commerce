<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Resources\OrderResource\Schemas;

use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use Filament\Forms;
use Filament\Schemas\Components\Section;

class OrderForm
{
    /**
     * @return array<int, Section>
     */
    public static function schema(): array
    {
        return [
            Section::make('Order Information')
                ->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->label('Order Number')
                        ->disabled()
                        ->columnSpan(1),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options(OrderResource::getStatusOptions())
                        ->disabled()
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('notes')
                        ->label('Customer Notes')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Internal Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Totals')
                ->schema([
                    Forms\Components\TextInput::make('subtotal')
                        ->label('Subtotal')
                        ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                        ->numeric()
                        ->disabled(),

                    Forms\Components\TextInput::make('discount_total')
                        ->label('Discount')
                        ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                        ->numeric()
                        ->disabled(),

                    Forms\Components\TextInput::make('shipping_total')
                        ->label('Shipping')
                        ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                        ->numeric()
                        ->disabled(),

                    Forms\Components\TextInput::make('tax_total')
                        ->label('Tax')
                        ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                        ->numeric()
                        ->disabled(),

                    Forms\Components\TextInput::make('grand_total')
                        ->label('Grand Total')
                        ->prefix(fn (?Order $record): string => $record?->currency ?? (string) config('orders.currency.default', 'MYR'))
                        ->numeric()
                        ->disabled(),
                ])
                ->columns(5),
        ];
    }
}
