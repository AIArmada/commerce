<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaAddressFormatter;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaGeographyProvider;

it('formats Guatemalan addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a. Avenida "A" 10-13, zona 1',
        'line2' => 'Centro Vivo, torre 2, Apartamento 608',
        'city' => 'Guatemala',
        'postcode' => '01001',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("6a. Avenida \"A\" 10-13, zona 1\nCentro Vivo, torre 2, Apartamento 608\n01001 - Guatemala\nGuatemala");
});
it('formats Guatemalan Villa Canales addresses with the town postcode', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Principal 1',
        'city' => 'Villa Canales',
        'postcode' => '01065',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("Calle Principal 1\n01065 - Villa Canales\nGuatemala");
});

it('ships 340 municipalities under departments with parent links', function (): void {
    $areas = app(GuatemalaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(340)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gt:municipality:coban')->name)->toBe('Cobán')
        ->and($byId->get('gt:municipality:quetzaltenango')->name)->toBe('Quetzaltenango')
        ->and($byId->get('gt:municipality:ciudad-de-guatemala')->name)->toBe('Ciudad de Guatemala');
});

it('labels tiers Departamento and Municipio', function (): void {
    $provider = app(GuatemalaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['department' => 'Departamento', 'municipality' => 'Municipio'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
