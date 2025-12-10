<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLevelResource\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventoryLevelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stock Level Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('inventoryable_type')
                            ->label('Product Type')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),

                        TextEntry::make('inventoryable_id')
                            ->label('Product ID')
                            ->copyable(),
                    ]),
                ]),

            Section::make('Stock Quantities')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('quantity_on_hand')
                            ->label('On Hand')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('info'),

                        TextEntry::make('quantity_reserved')
                            ->label('Reserved')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('warning'),

                        TextEntry::make('available')
                            ->label('Available')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color(fn ($record): string => $record->getAvailableQuantity() <= $record->reorder_point ? 'danger' : 'success')
                            ->state(fn ($record): int => $record->getAvailableQuantity()),

                        TextEntry::make('reorder_point')
                            ->label('Reorder Point')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->color('gray'),
                    ]),
                ]),

            Section::make('Configuration')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('allocation_strategy')
                            ->label('Allocation Strategy')
                            ->badge()
                            ->color('primary')
                            ->placeholder('Using global default'),

                        TextEntry::make('effective_strategy')
                            ->label('Effective Strategy')
                            ->state(fn ($record): string => $record->getEffectiveAllocationStrategy()->label())
                            ->badge()
                            ->color('success'),
                    ]),
                ]),

            Section::make('Timestamps')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ]),
                ])
                ->collapsed(),
        ]);
    }
}
