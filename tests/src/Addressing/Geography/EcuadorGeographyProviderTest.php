<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ecuador\EcuadorAddressFormatter;
use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(EcuadorGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ecuadorian tree with state links', function (): void {
    $this->seedCountry('EC');

    $result = app(SeedCountryGeographiesAction::class)->execute('EC');
    $country = AddressCountry::query()->where('iso2', 'EC')->firstOrFail();

    expect($result['seeded'])->toContain('EC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(24)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(24);
});

it('formats Ecuadorian addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Francisco Dalmau 547 y Calle 2',
        'line2' => 'Conjunto Real Audiencia, Bloque 3',
        'line3' => 'Conocoto',
        'city' => 'QUITO',
        'postcode' => '170303',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Francisco Dalmau 547 y Calle 2\nConjunto Real Audiencia, Bloque 3\nConocoto\n170303 - QUITO\nEcuador");
});
it('formats Ecuadorian Guayaquil addresses with the zone postcode', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. 9 de Octubre 100',
        'city' => 'GUAYAQUIL',
        'postcode' => '090306',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Av. 9 de Octubre 100\n090306 - GUAYAQUIL\nEcuador");
});
