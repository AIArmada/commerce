<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventoryAllocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Allocation Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('id')
                            ->label('Allocation ID')
                            ->copyable()
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('primary'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->state(fn ($record): string => $record->isExpired() ? 'Expired' : 'Active')
                            ->badge()
                            ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success'),
                    ]),
                ]),

            Section::make('Product Information')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('inventoryable_type')
                            ->label('Product Type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),

                        TextEntry::make('inventoryable_id')
                            ->label('Product ID')
                            ->copyable(),
                    ]),
                ]),

            Section::make('Location & Cart')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('cart_id')
                            ->label('Cart ID')
                            ->copyable()
                            ->placeholder('—'),
                    ]),
                ]),

            Section::make('Timing')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('expires_at')
                            ->label('Expires At')
                            ->dateTime()
                            ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'success'),

                        TextEntry::make('time_remaining')
                            ->label('Time Remaining')
                            ->state(fn ($record): string => $record->isExpired() 
                                ? 'Expired ' . $record->expires_at->diffForHumans()
                                : $record->expires_at->diffForHumans(['parts' => 2])
                            )
                            ->color(fn ($record): string => $record->isExpired() ? 'danger' : 'info'),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT) : '—')
                        ->prose()
                        ->markdown(),
                ])
                ->collapsed(),
        ]);
    }
}
