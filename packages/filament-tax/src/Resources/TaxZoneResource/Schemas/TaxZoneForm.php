<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources\TaxZoneResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TaxZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Group::make()
                    ->schema([
                        Section::make('Zone Details')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Zone Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique identifier (e.g., MY, MY-SEL)'),

                                Select::make('type')
                                    ->label('Zone Type')
                                    ->options([
                                        'country' => 'Country',
                                        'state' => 'State/Region',
                                        'postcode' => 'Postcode Range',
                                    ])
                                    ->default('country')
                                    ->required(),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Section::make('Geographic Matching')
                            ->schema([
                                TagsInput::make('countries')
                                    ->label('Countries')
                                    ->helperText('ISO country codes (MY, SG, etc.)')
                                    ->placeholder('Add country code'),

                                TagsInput::make('states')
                                    ->label('States/Regions')
                                    ->helperText('State names or codes')
                                    ->placeholder('Add state'),

                                TagsInput::make('postcodes')
                                    ->label('Postcodes')
                                    ->helperText('Exact postcodes, ranges (10000-19999), or wildcards (50*)')
                                    ->placeholder('Add postcode pattern'),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Settings')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Toggle::make('is_default')
                                    ->label('Default Zone')
                                    ->helperText('Used when no other zone matches'),

                                TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Higher = checked first'),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
