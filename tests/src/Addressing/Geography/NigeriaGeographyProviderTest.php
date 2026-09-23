<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;
use AIArmada\Addressing\Geography\Nigeria\NigeriaGeographyProvider;

it('formats Nigerian addresses with the postcode right and state below', function (): void {
    $formatted = app(NigeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => '34 Alayande Cl',
        'city' => 'Mokola',
        'state' => 'OYO STATE',
        'postcode' => '200212',
        'country_code' => 'NG',
    ]));

    expect($formatted)->toBe("34 Alayande Cl\nMokola 200212\nOYO STATE\nNigeria");
});

it('ships 768 LGAs and 6 area councils under states with parent links', function (): void {
    $areas = app(NigeriaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(774)
        ->and($areas->where('type', 'lga'))->toHaveCount(768)
        ->and($areas->where('type', 'area_council'))->toHaveCount(6)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ng:lga:abia:aba-north')->name)->toBe('Aba North')
        ->and($byId->get('ng:area_council:abuja-federal-capital-territory:abaji')->name)->toBe('Abaji');
});

it('labels LGAs with their acronym', function (): void {
    $provider = app(NigeriaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['lga' => 'LGA'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
