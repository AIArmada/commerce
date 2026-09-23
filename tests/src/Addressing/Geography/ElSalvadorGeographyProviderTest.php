<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorAddressFormatter;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;

it('formats Salvadoran addresses with the postcode left of the locality', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a AVENIDA NORTE 165',
        'city' => 'SAN SALVADOR',
        'postcode' => '2201',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("6a AVENIDA NORTE 165\n2201 SAN SALVADOR\nEl Salvador");
});
it('formats Salvadoran box addresses with the branch postcode', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 131',
        'line2' => 'SUCURSAL SOPAYANGO',
        'city' => 'SAN SALVADOR',
        'postcode' => '1116',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 131\nSUCURSAL SOPAYANGO\n1116 SAN SALVADOR\nEl Salvador");
});

it('ships 44 municipalitys under departments with parent links', function (): void {
    $areas = app(ElSalvadorGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(44)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sv:municipality:central-ahuachapan')->name)->toBe('Central Ahuachapán')
        ->and($byId->get('sv:municipality:northern-ahuachapan')->name)->toBe('Northern Ahuachapán')
        ->and($byId->get('sv:municipality:southern-ahuachapan')->name)->toBe('Southern Ahuachapán');
});

it('labels tiers Departamento and Municipio', function (): void {
    $provider = app(ElSalvadorGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['department' => 'Departamento', 'municipality' => 'Municipio'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
