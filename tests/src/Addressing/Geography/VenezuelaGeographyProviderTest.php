<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaAddressFormatter;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(VenezuelaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Venezuelan tree with state links', function (): void {
    $this->seedCountry('VE');

    $result = app(SeedCountryGeographiesAction::class)->execute('VE');
    $country = AddressCountry::query()->where('iso2', 'VE')->firstOrFail();

    expect($result['seeded'])->toContain('VE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(23)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_district')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'federal_dependency')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);
});

it('formats Venezuelan addresses with the postcode right of the locality', function (): void {
    $formatted = app(VenezuelaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AV. FUERZAS ARMADAS',
        'line2' => 'TORRE SAN JOSÉ, ENTRADA B',
        'line3' => 'PISO 5, APARTAMENTO 20',
        'city' => 'CARACAS',
        'state' => 'D.C.',
        'postcode' => '1010',
        'country_code' => 'VE',
    ]));

    expect($formatted)->toBe("AV. FUERZAS ARMADAS\nTORRE SAN JOSÉ, ENTRADA B\nPISO 5, APARTAMENTO 20\nCARACAS 1010\nD.C.\nVenezuela");
});
it('formats Venezuelan addresses passing extended codes through', function (): void {
    $formatted = app(VenezuelaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Bolívar 3',
        'city' => 'SANARE',
        'state' => 'LARA',
        'postcode' => '3028-A',
        'country_code' => 'VE',
    ]));

    expect($formatted)->toBe("Calle Bolívar 3\nSANARE 3028-A\nLARA\nVenezuela");
});
