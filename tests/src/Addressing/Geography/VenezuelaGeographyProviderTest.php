<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaAddressFormatter;
use AIArmada\Addressing\Geography\Venezuela\VenezuelaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

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

it('names the W federal dependency Dependencias Federales', function (): void {
    $area = app(VenezuelaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId->get('ve:federal_dependency:dependencias-federales');

    expect($area->name)->toBe('Dependencias Federales')
        ->and($area->code)->toBe('W');
});

it('ships 335 municipalities under states with parent links', function (): void {
    $areas = app(VenezuelaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(335)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ve:municipality:chacao')->name)->toBe('Chacao')
        ->and($byId->get('ve:municipality:baruta')->name)->toBe('Baruta')
        ->and($byId->get('ve:municipality:libertador-bolivarian')->name)->toBe('Libertador');
});

it('roles the capital district with the state selector', function (): void {
    $roles = app(VenezuelaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['ve:capital_district:distrito-capital'][0]['role'])->toBe('state')
        ->and($roles['ve:federal_dependency:dependencias-federales'][0]['role'])->toBe('federal_dependency');
});

it('labels tiers Estado, Distrito Capital, Dependencias Federales and Municipio', function (): void {
    $provider = app(VenezuelaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['state' => 'Estado', 'capital_district' => 'Distrito Capital', 'federal_dependency' => 'Dependencias Federales', 'municipality' => 'Municipio'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
