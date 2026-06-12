<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class AddressCountryFormSchema
{
    public static function make(): array
    {
        return [
            TextInput::make('iso2')
                ->label('ISO2')
                ->required()
                ->maxLength(2),
            TextInput::make('iso3')
                ->label('ISO3')
                ->maxLength(3),
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),
            TextInput::make('official_name')
                ->label('Official Name')
                ->maxLength(255),
            TextInput::make('native_name')
                ->label('Native Name')
                ->maxLength(255),
            TextInput::make('phone_code')
                ->label('Phone Code')
                ->maxLength(50),
            TextInput::make('default_currency_code')
                ->label('Default Currency Code')
                ->maxLength(3),
            TextInput::make('region')
                ->label('Region')
                ->maxLength(255),
            TextInput::make('subregion')
                ->label('Subregion')
                ->maxLength(255),
            Select::make('entity_type')
                ->label('Entity Type')
                ->options([
                    'country' => 'Country',
                    'territory' => 'Territory',
                    'dependent' => 'Dependent',
                    'special' => 'Special',
                    'disputed' => 'Disputed',
                ]),
            Toggle::make('is_independent')
                ->label('Independent'),
        ];
    }
}
