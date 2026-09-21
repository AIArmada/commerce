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
