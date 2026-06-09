<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources\CustomerResource\Schemas;

use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Customer Overview')
                    ->schema([
                        TextEntry::make('full_name')
                            ->label('Name'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label('Phone')
                            ->copyable(),
                        TextEntry::make('company')
                            ->label('Company')
                            ->placeholder('Not set'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->color(fn ($state) => $state->color()),
                    ])
                    ->columns(4),

                Section::make('Preferences')
                    ->schema([
                        TextEntry::make('accepts_marketing')
                            ->label('Accepts Marketing')
                            ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                    ])
                    ->columns(1),

                Section::make('Activity')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Customer Since')
                            ->dateTime(),
                    ])
                    ->columns(1),

                Section::make('Segments')
                    ->schema([
                        TextEntry::make('segments.name')
                            ->label('Assigned Segments')
                            ->badge()
                            ->placeholder('No segments'),
                    ]),

                Section::make('Related Entities')
                    ->description('Cross-navigation to related resources')
                    ->schema([
                        TextEntry::make('orders_link')
                            ->label('Orders')
                            ->default('View orders for this customer')
                            ->url(fn ($record): ?string => $record?->id
                                ? (string) OrderResource::getUrl('index', [
                                    'customer_id' => $record->id,
                                ])
                                : null, shouldOpenInNewTab: true)
                            ->visible(fn (): bool => class_exists(OrderResource::class)),

                        TextEntry::make('carts_link')
                            ->label('Carts')
                            ->default('View cart history for this customer')
                            ->url(fn ($record): ?string => $record?->id
                                ? (string) CartResource::getUrl('index', [
                                    'customer_id' => $record->id,
                                ])
                                : null, shouldOpenInNewTab: true)
                            ->visible(fn (): bool => class_exists(CartResource::class)),

                        TextEntry::make('vouchers_link')
                            ->label('Vouchers')
                            ->default('View vouchers for this customer')
                            ->url(fn ($record): ?string => $record?->id
                                ? (string) VoucherResource::getUrl('index', [
                                    'customer_id' => $record->id,
                                ])
                                : null, shouldOpenInNewTab: true)
                            ->visible(fn (): bool => class_exists(VoucherResource::class)),

                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
