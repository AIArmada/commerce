<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

final class ShippingZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Zone Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->required()
                            ->rules(function ($record) {
                                $owner = OwnerContext::resolve();
                                $rule = Rule::unique(ShippingZone::class, 'code')
                                    ->where('owner_type', $owner?->getMorphClass())
                                    ->where('owner_id', $owner?->getKey());

                                if ($record !== null) {
                                    $rule = $rule->ignore($record->id);
                                }

                                return [$rule];
                            })
                            ->maxLength(50),

                        Select::make('type')
                            ->options([
                                'country' => 'Country',
                                'state' => 'State/Province',
                                'postcode' => 'Postcode Range',
                                'radius' => 'Radius from Point',
                            ])
                            ->required()
                            ->live(),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority zones are checked first'),

                        Toggle::make('is_default')
                            ->label('Default Zone')
                            ->helperText('Fallback for addresses that don\'t match any zone'),

                        Toggle::make('active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Geographic Conditions')
                    ->schema([
                        TagsInput::make('countries')
                            ->placeholder('Add country codes (e.g., MYS, SGP)')
                            ->visible(fn (Get $get) => in_array($get('type'), ['country', 'state'])),

                        TagsInput::make('states')
                            ->placeholder('Add state names')
                            ->visible(fn (Get $get) => $get('type') === 'state'),

                        Repeater::make('postcode_ranges')
                            ->schema([
                                TextInput::make('from')
                                    ->required()
                                    ->maxLength(20),
                                TextInput::make('to')
                                    ->required()
                                    ->maxLength(20),
                            ])
                            ->columns(2)
                            ->visible(fn (Get $get) => $get('type') === 'postcode'),

                        Grid::make()
                            ->schema([
                                TextInput::make('center_lat')
                                    ->label('Latitude')
                                    ->numeric(),
                                TextInput::make('center_lng')
                                    ->label('Longitude')
                                    ->numeric(),
                                TextInput::make('radius_km')
                                    ->label('Radius (km)')
                                    ->numeric(),
                            ])
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('type') === 'radius'),
                    ])
                    ->visible(fn (Get $get) => $get('type') !== null),
            ]);
    }
}
