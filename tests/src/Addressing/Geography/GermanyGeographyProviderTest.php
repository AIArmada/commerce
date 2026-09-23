<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;
use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;

it('formats German addresses with the postcode left of the locality', function (): void {
    $formatted = app(GermanyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wacholderweg 52a',
        'city' => 'OLDENBURG',
        'postcode' => '26133',
        'country_code' => 'DE',
    ]));

    expect($formatted)->toBe("Wacholderweg 52a\n26133 OLDENBURG\nGermany");
});

it('ships 294 rural and 107 urban districts under states with parent links', function (): void {
    $areas = app(GermanyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'rural_district'))->toHaveCount(294)
        ->and($areas->where('type', 'urban_district'))->toHaveCount(107)
        ->and($byId->get('de:district:berlin')->type)->toBe('urban_district')
        ->and($byId->get('de:district:munich')->type)->toBe('rural_district')
        ->and($byId->get('de:district:bayern:munich')->type)->toBe('urban_district')
        ->and($byId->get('de:district:aachen')->type)->toBe('rural_district');
});

it('labels states Land and district forms Landkreis and Kreisfreie Stadt', function (): void {
    $provider = app(GermanyGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['state' => 'Land', 'rural_district' => 'Landkreis', 'urban_district' => 'Kreisfreie Stadt'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
