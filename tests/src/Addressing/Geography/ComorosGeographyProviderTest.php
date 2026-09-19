<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Comoros\ComorosAddressFormatter;
use AIArmada\Addressing\Geography\Comoros\ComorosGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ComorosGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('island')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Comorian tree with state links', function (): void {
    $this->seedCountry('KM');

    $result = app(SeedCountryGeographiesAction::class)->execute('KM');
    $country = AddressCountry::query()->where('iso2', 'KM')->firstOrFail();

    expect($result['seeded'])->toContain('KM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'island')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Comorian addresses without a postcode system', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 350',
        'city' => 'MORONI',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("BP 350\nMORONI\nComoros");
});
it('formats Comorian addresses with the island below the locality', function (): void {
    $formatted = app(ComorosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Corniche',
        'city' => 'Moroni',
        'state' => 'Grande Comore',
        'country_code' => 'KM',
    ]));

    expect($formatted)->toBe("Rue de la Corniche\nMoroni\nGrande Comore\nComoros");
});
