<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Schemas;

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\ModelResolver;
use AIArmada\FilamentAddressing\Rules\AddressAreasBelongToCountry;
use AIArmada\FilamentAddressing\Rules\StateBelongsToCountry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class AddressFormSchema
{
    public static function make(string $prefix = '', ?Address $record = null): array
    {
        $fields = [];
        $assignmentValues = $record?->areaAssignments()->pluck('address_area_id', 'role')->toArray() ?? [];

        $fields[] = Select::make($prefix . 'country_code')
            ->label('Country')
            ->options(
                config('filament-addressing.resources.countries.model', AddressCountry::class)::query()
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (AddressCountry $country): array => [
                        $country->iso2 => "{$country->iso2} — {$country->name}",
                    ])
                    ->toArray(),
            )
            ->searchable()
            ->required()
            ->live()
            ->afterStateUpdated(function (callable $set) use ($prefix): void {
                foreach (['state_id', 'postal_area_id', 'administrative_district_id', 'administrative_subdivision_id', 'administrative_lower_area_id'] as $field) {
                    $set($prefix . $field, null);
                }
            });

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
            ->label('City / Locality (free text)')
            ->maxLength(255);

        $fields[] = Select::make($prefix . 'state_id')
            ->label('State / Federal Territory')
            ->options(fn (callable $get): array => ModelResolver::stateClass()::query()
                ->when($get($prefix . 'country_code'), fn ($query, string $countryCode) => $query->whereHas('country', fn ($countries) => $countries->where('iso2', $countryCode)))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray())
            ->searchable()
            ->rules(fn (callable $get): array => [new StateBelongsToCountry($get($prefix . 'country_code'))])
            ->live();

        foreach ([
            'postal_area_id' => ['label' => 'Postal locality / precinct / kampung', 'role' => 'postal_locality'],
            'administrative_district_id' => ['label' => 'Administrative district / division / jajahan', 'role' => 'administrative_district'],
            'administrative_subdivision_id' => ['label' => 'Administrative mukim / subdistrict / bandar / pekan', 'role' => 'administrative_subdivision'],
            'administrative_lower_area_id' => ['label' => 'Additional administrative area', 'role' => 'administrative_lower_area'],
        ] as $field => $definition) {
            $areaClass = config('filament-addressing.resources.areas.model', AddressArea::class);
            $role = $definition['role'];

            $fields[] = Select::make($prefix . $field)
                ->label($definition['label'])
                ->getSearchResultsUsing(function (string $search, callable $get) use ($prefix, $areaClass, $role): array {
                    $countryCode = $get($prefix . 'country_code');

                    if (! is_string($countryCode) || mb_trim($countryCode) === '') {
                        return [];
                    }

                    return $areaClass::query()
                        ->where('country_code', mb_strtoupper($countryCode))
                        ->whereHas('roles', fn ($roles) => $roles->where('role', str_starts_with($role, 'postal_') ? 'locality' : 'administrative_area'))
                        ->where(function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(50)
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->getOptionLabelUsing(fn (mixed $value): ?string => $value === null ? null : $areaClass::query()->whereKey($value)->value('name'))
                ->searchable()
                ->rules(fn (callable $get): array => [new AddressAreasBelongToCountry($get($prefix . 'country_code'))])
                ->default($assignmentValues[$role] ?? null)
                ->dehydrated(false)
                ->live();
        }

        $fields[] = TextInput::make($prefix . 'postcode')
            ->label('Postcode')
            ->maxLength(20);

        return $fields;
    }
}
