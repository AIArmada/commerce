<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweGeographyProvider;

it('formats Zimbabwean addresses without a postcode system', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '34–6th Crescent',
        'line2' => 'Warren Park 1',
        'city' => 'HARARE',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("34–6th Crescent\nWarren Park 1\nHARARE\nZimbabwe");
});
it('prints matching Zimbabwean city and province once', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Josiah Tongogara Street',
        'city' => 'Bulawayo',
        'state' => 'Bulawayo',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("12 Josiah Tongogara Street\nBulawayo\nZimbabwe");
});

it('ships 64 districts under provinces with parent links', function (): void {
    $areas = app(ZimbabweGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(64)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('zw:district:harare')->name)->toBe('Harare')
        ->and($byId->get('zw:district:bulawayo')->name)->toBe('Bulawayo')
        ->and($byId->get('zw:district:mutare')->name)->toBe('Mutare');
});
