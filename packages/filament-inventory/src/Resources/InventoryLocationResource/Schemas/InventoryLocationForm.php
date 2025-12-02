<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Unique identifier for this location'),
                    ]),

                    Textarea::make('address')
                        ->label('Address')
                        ->rows(3),

                    Grid::make(2)->schema([
                        TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority locations are used first for allocation'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive locations are excluded from allocation'),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Additional Data')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Field'),
                ])
                ->collapsed(),
        ]);
    }
}
