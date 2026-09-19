<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Honduras\HondurasAddressFormatter;
use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HondurasGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Honduran tree with state links', function (): void {
    $this->seedCountry('HN');

    $result = app(SeedCountryGeographiesAction::class)->execute('HN');
    $country = AddressCountry::query()->where('iso2', 'HN')->firstOrFail();

    expect($result['seeded'])->toContain('HN')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});

it('formats Honduran addresses with the 5-digit postcode left of the city', function (): void {
    $formatted = app(HondurasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Barrio El Centro, 3era Avenida',
        'city' => 'Tegucigalpa',
        'state' => 'Francisco Morazán',
        'postcode' => '11101',
        'country_code' => 'HN',
    ]));

    expect($formatted)->toBe("Barrio El Centro, 3era Avenida\n11101 Tegucigalpa\nFrancisco Morazán\nHonduras");
});
it('formats Honduran addresses passing legacy alphanumeric codes through', function (): void {
    $formatted = app(HondurasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Barrio El Centro',
        'city' => 'LAS LAJAS',
        'state' => 'COMAYAGUA',
        'postcode' => 'CM1102',
        'country_code' => 'HN',
    ]));

    expect($formatted)->toBe("Barrio El Centro\nCM1102 LAS LAJAS\nCOMAYAGUA\nHonduras");
});
