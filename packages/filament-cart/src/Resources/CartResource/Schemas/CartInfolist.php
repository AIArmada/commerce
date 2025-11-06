<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources\CartResource\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class CartInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cart Overview')
                    ->schema([
                        //                        Split::make([
                        //                            Grid::make(2)
                        //                                ->schema([
                        TextEntry::make('identifier')
                            ->label('Cart ID')
                            ->copyable()
                            ->icon(Heroicon::OutlinedIdentification),

                        TextEntry::make('instance')
                            ->label('Instance')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'default' => 'gray',
                                'wishlist' => 'warning',
                                'comparison' => 'info',
                                'quote' => 'success',
                                default => 'primary',
                            }),

                        TextEntry::make('items_count')
                            ->label('Total Items')
                            ->icon(Heroicon::OutlinedShoppingBag),

                        TextEntry::make('quantity')
                            ->label('Total Quantity')
                            ->icon(Heroicon::OutlinedHashtag),

                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->formatStateUsing(fn ($state, $record) => $record->formatMoney((int) $state))
                            ->icon(Heroicon::OutlinedCurrencyDollar),

                        TextEntry::make('total')
                            ->label('Total After Conditions')
                            ->formatStateUsing(fn ($state, $record) => $record->formatMoney((int) $state))
                            ->icon(Heroicon::OutlinedCurrencyDollar)
                            ->color('success'),

                        TextEntry::make('savings')
                            ->label('Savings')
                            ->formatStateUsing(fn ($state, $record) => $record->formatMoney((int) $state))
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->color('success')
                            ->visible(fn ($record) => $record->savings > 0),

                        TextEntry::make('currency')
                            ->label('Currency')
                            ->badge()
                            ->icon(Heroicon::OutlinedGlobeAsiaAustralia),

                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime()
                            ->icon(Heroicon::OutlinedCalendar),
                        //                                ]),
                        //                        ]),
                    ]),
                //
                //                Section::make('Cart Items')
                //                    ->schema([
                //                        RepeatableEntry::make('items')
                //                            ->label('')
                //                            ->schema([
                // //                                Split::make([
                // //                                    Grid::make(2)
                // //                                        ->schema([
                //                                            TextEntry::make('id')
                //                                                ->label('Product ID')
                //                                                ->copyable(),
                //
                //                                            TextEntry::make('name')
                //                                                ->label('Product Name')
                //                                                ->weight('semibold'),
                //
                //                                            TextEntry::make('quantity')
                //                                                ->label('Quantity')
                //                                                ->icon(Heroicon::OutlinedHashtag),
                //
                //                                            TextEntry::make('price')
                //                                                ->label('Unit Price')
                //                                                ->money('USD')
                //                                                ->icon(Heroicon::OutlinedCurrencyDollar),
                // //                                        ]),
                //
                // //                                    Grid::make(1)
                // //                                        ->schema([
                //                                            TextEntry::make('subtotal')
                //                                                ->label('Subtotal')
                //                                                ->getStateUsing(fn (array $state): string =>
                //                                                    '$' . number_format(($state['price'] ?? 0) * ($state['quantity'] ?? 0), 2)
                //                                                )
                //                                                ->color('success')
                //                                                ->weight('semibold'),
                //
                //                                            KeyValueEntry::make('attributes')
                //                                                ->label('Attributes')
                //                                                ->visible(fn (array $state): bool =>
                //                                                    !empty($state['attributes'])
                //                                                ),
                // //                                        ]),
                // //                                ]),
                //                            ])
                //                            ->contained(false)
                //                            ->visible(fn ($record): bool => !$record->isEmpty()),
                //                    ])
                //                    ->collapsible()
                //                    ->visible(fn ($record): bool => !$record->isEmpty()),

                Section::make('Metadata')
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('Additional Information'),
                    ])
                    ->collapsible()
                    ->visible(fn ($record): bool => is_array($record->metadata) && ! empty($record->metadata)
                    ),

                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime()
                                    ->icon(Heroicon::OutlinedCalendar),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->since()
                                    ->icon(Heroicon::OutlinedClock),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
