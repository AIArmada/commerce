<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryMovementResource\Schemas;

use AIArmada\Inventory\Enums\MovementType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

final class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Movement Details')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge()
                            ->color(fn (MovementType $state): string => match ($state) {
                                MovementType::Receipt => 'success',
                                MovementType::Shipment => 'info',
                                MovementType::Transfer => 'warning',
                                MovementType::Adjustment => 'gray',
                                MovementType::Allocation => 'primary',
                                MovementType::Release => 'danger',
                            }),

                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->formatStateUsing(fn (int $state): string => $state >= 0 ? "+{$state}" : (string) $state)
                            ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger'),

                        TextEntry::make('created_at')
                            ->label('Date/Time')
                            ->dateTime(),
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

            Section::make('Location Information')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('fromLocation.name')
                            ->label('From Location')
                            ->placeholder('—')
                            ->weight(FontWeight::SemiBold),

                        TextEntry::make('toLocation.name')
                            ->label('To Location')
                            ->placeholder('—')
                            ->weight(FontWeight::SemiBold),
                    ]),
                ]),

            Section::make('Additional Information')
                ->schema([
                    TextEntry::make('reason')
                        ->label('Reason')
                        ->placeholder('—'),

                    Grid::make(2)->schema([
                        TextEntry::make('reference_type')
                            ->label('Reference Type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                            ->placeholder('—'),

                        TextEntry::make('reference_id')
                            ->label('Reference ID')
                            ->copyable()
                            ->placeholder('—'),
                    ]),

                    Grid::make(2)->schema([
                        TextEntry::make('user_id')
                            ->label('User ID')
                            ->copyable()
                            ->placeholder('System'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
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
