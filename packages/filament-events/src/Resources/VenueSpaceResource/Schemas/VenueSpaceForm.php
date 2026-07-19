<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueSpaceResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VenueSpaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Space Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('code')
                            ->maxLength(255),
                        TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1),
                        Select::make('space_type')
                            ->options([
                                'hall' => 'Hall',
                                'room' => 'Room',
                                'outdoor' => 'Outdoor',
                            ])
                            ->placeholder('Select type'),
                        TextInput::make('level')
                            ->label('Floor / Level')
                            ->maxLength(255),
                        TextInput::make('unit_no')
                            ->label('Unit No.')
                            ->maxLength(255),
                        TextInput::make('block')
                            ->maxLength(255),
                        TextInput::make('wing')
                            ->maxLength(255),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'unlisted' => 'Unlisted',
                                'private' => 'Private',
                            ])
                            ->default('public')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
