<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Panama\PanamaAddressFormatter;
use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PanamaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Panamanian tree with state links', function (): void {
    $this->seedCountry('PA');

    $result = app(SeedCountryGeographiesAction::class)->execute('PA');
    $country = AddressCountry::query()->where('iso2', 'PA')->firstOrFail();

    expect($result['seeded'])->toContain('PA')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'indigenous_region')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Panamanian addresses without a postcode system', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIA ESPAÑA, CALLE 6ta',
        'line2' => 'EDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("VIA ESPAÑA, CALLE 6ta\nEDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});
it('formats Panamanian box addresses keeping the zone box in the street line', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 0832-02345',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 0832-02345\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});
