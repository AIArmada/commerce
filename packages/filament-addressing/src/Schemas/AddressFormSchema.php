<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Models\AddressCountry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class AddressFormSchema
{
    public static function make(string $prefix = ''): array
    {
        $fields = [];

        $fields[] = TextInput::make($prefix . 'label')
            ->label('Label')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'line1')
            ->label('Line 1')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'line2')
            ->label('Line 2')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'city')
            ->label('City / Locality')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'district')
            ->label('District')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'state')
            ->label('State / Region')
            ->maxLength(255);

        $fields[] = TextInput::make($prefix . 'postcode')
            ->label('Postcode')
            ->maxLength(20);

        $fields[] = Select::make($prefix . 'country_code')
            ->label('Country')
            ->options(
                AddressCountry::query()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (AddressCountry $country): array => [
                        $country->iso2 => "{$country->iso2} — {$country->name}",
                    ])
                    ->toArray(),
            )
            ->searchable()
            ->required();

        return $fields;
    }
}
