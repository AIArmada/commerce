<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock\Resources\StockReservationResource\Schemas;

use AIArmada\FilamentStock\Support\FilamentCartBridge;
use AIArmada\Stock\Models\StockReservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class StockReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation Overview')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('id')
                                ->label('Reservation ID')
                                ->copyable()
                                ->badge(),

                            TextEntry::make('quantity')
                                ->label('Quantity Reserved')
                                ->badge()
                                ->color('warning'),

                            TextEntry::make('status')
                                ->label('Status')
                                ->state(fn (StockReservation $record): string => $record->isValid() ? 'Active' : 'Expired')
                                ->badge()
                                ->color(fn (StockReservation $record): string => $record->isValid() ? 'success' : 'danger'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextEntry::make('expires_at')
                                ->label('Expires At')
                                ->dateTime(),

                            TextEntry::make('time_remaining')
                                ->label('Time Remaining')
                                ->state(function (StockReservation $record): string {
                                    if ($record->isExpired()) {
                                        return 'Expired';
                                    }

                                    return $record->expires_at->diffForHumans(now(), [
                                        'parts' => 2,
                                        'short' => true,
                                    ]);
                                })
                                ->badge()
                                ->color(fn (StockReservation $record): string => $record->isValid() ? 'info' : 'danger'),
                        ]),
                ]),

            Section::make('Cart Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('cart_id')
                                ->label('Cart ID')
                                ->copyable()
                                ->url(function (StockReservation $record): ?string {
                                    $bridge = app(FilamentCartBridge::class);

                                    return $bridge->getCartUrl($record->cart_id);
                                })
                                ->openUrlInNewTab(),

                            TextEntry::make('cart_link_status')
                                ->label('Cart Integration')
                                ->state(
                                    fn (): string => app(FilamentCartBridge::class)->isAvailable()
                                    ? 'Linked'
                                    : 'Not Available'
                                )
                                ->badge()
                                ->color(
                                    fn (): string => app(FilamentCartBridge::class)->isAvailable()
                                    ? 'success'
                                    : 'gray'
                                ),
                        ]),
                ]),

            Section::make('Stockable Item')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('stockable_type')
                                ->label('Item Type')
                                ->formatStateUsing(fn (string $state): string => class_basename($state)),

                            TextEntry::make('stockable_id')
                                ->label('Item ID')
                                ->copyable(),
                        ]),
                ]),

            Section::make('Timestamps')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),

                            TextEntry::make('updated_at')
                                ->label('Updated')
                                ->dateTime(),
                        ]),
                ])
                ->collapsed(),
        ]);
    }
}
