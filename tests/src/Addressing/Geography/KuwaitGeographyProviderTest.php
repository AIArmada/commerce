<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;

it('formats Kuwaiti addresses with the postcode left of the locality', function (): void {
    $formatted = app(KuwaitAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al-Sabbahiya',
        'city' => 'KUWAIT',
        'postcode' => '54551',
        'country_code' => 'KW',
    ]));

    expect($formatted)->toBe("Al-Sabbahiya\n54551 KUWAIT\nKuwait");
});
it('exposes the Kuwait City area under Al Asimah', function (): void {
    $areas = app(KuwaitGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    $city = $areas->get('kw:area:kuwait-city');

    expect($city->name)->toBe('Kuwait City')
        ->and($city->code)->toBeNull()
        ->and($city->type)->toBe('area')
        ->and($city->level)->toBe(2)
        ->and($city->parentSourceId)->toBe('kw:governorate:al-asimah');
});

it('ships 135 areas under governorates with parent links', function (): void {
    $areas = app(KuwaitGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'area');

    expect($l2)->toHaveCount(135)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('kw:area:adailiya')->name)->toBe('Adailiya')
        ->and($byId->get('kw:area:abdulla-al-salem')->parentSourceId)->toBe('kw:governorate:al-asimah');
});

it('labels governorates Muhafaza', function (): void {
    $provider = app(KuwaitGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['governorate' => 'Muhafaza'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
