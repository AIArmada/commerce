<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jordan\JordanAddressFormatter;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;

it('formats Jordanian addresses with the postcode right of the locality', function (): void {
    $formatted = app(JordanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Mohazab Al Halabi',
        'city' => 'AMMAN',
        'postcode' => '11937',
        'country_code' => 'JO',
    ]));

    expect($formatted)->toBe("Al Mohazab Al Halabi\nAMMAN 11937\nJordan");
});

it('ships 51 liwa under governorates with parent links', function (): void {
    $areas = app(JordanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'liwa');

    expect($l2)->toHaveCount(51)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('jo:liwa:ajloun:ajloun-qasabah')->name)->toBe('Ajloun Qasabah')
        ->and($byId->get('jo:liwa:ajloun:kufranjah')->parentSourceId)->toBe('jo:governorate:ajloun');
});

it('labels tiers Muhafaza and Liwa', function (): void {
    $provider = app(JordanGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['governorate' => 'Muhafaza', 'liwa' => 'Liwa'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
