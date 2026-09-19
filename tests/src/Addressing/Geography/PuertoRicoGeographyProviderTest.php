<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoAddressFormatter;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PuertoRicoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Puerto Rican tree with state links', function (): void {
    $this->seedCountry('PR');

    $result = app(SeedCountryGeographiesAction::class)->execute('PR');
    $country = AddressCountry::query()->where('iso2', 'PR')->firstOrFail();

    expect($result['seeded'])->toContain('PR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(68)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(78);
});

it('formats Puerto Rican addresses as locality PR ZIP', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'URB LAS GLADIOLAS',
        'line2' => '150 CALLE A',
        'city' => 'SAN JUAN',
        'postcode' => '00926-0221',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("URB LAS GLADIOLAS\n150 CALLE A\nSAN JUAN PR 00926-0221\nPuerto Rico");
});
it('formats Puerto Rican addresses keeping the barrio above the ZIP line', function (): void {
    $formatted = app(PuertoRicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Luna 10',
        'city' => 'Santurce',
        'state' => 'San Juan',
        'postcode' => '00907',
        'country_code' => 'PR',
    ]));

    expect($formatted)->toBe("Calle Luna 10\nSanturce\nSan Juan PR 00907\nPuerto Rico");
});
