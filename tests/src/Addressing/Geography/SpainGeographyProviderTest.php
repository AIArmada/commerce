<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;
use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;

it('formats Spanish addresses with the province on its own line', function (): void {
    $formatted = app(SpainAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Huertas 18, 4º, C',
        'city' => 'MARBELLA',
        'state' => 'MÁLAGA',
        'postcode' => '29400',
        'country_code' => 'ES',
    ]));

    expect($formatted)->toBe("Calle Huertas 18, 4º, C\n29400 MARBELLA\nMÁLAGA\nSpain");
});

it('ships 50 provinces under communities plus Ceuta and Melilla with parent links', function (): void {
    $areas = app(SpainGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1->where('type', 'autonomous_community'))->toHaveCount(17)
        ->and($l1->where('type', 'autonomous_city'))->toHaveCount(2)
        ->and($areas->where('type', 'province'))->toHaveCount(50)
        ->and($byId->get('es:province:almeria')->parentSourceId)->toBe('es:autonomous_community:andalusia');
});

it('labels tiers Comunidad Autónoma, Ciudad Autónoma and Provincia', function (): void {
    $provider = app(SpainGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['autonomous_community' => 'Comunidad Autónoma', 'autonomous_city' => 'Ciudad Autónoma', 'province' => 'Provincia'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
