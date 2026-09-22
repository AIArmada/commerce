<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Yemen\YemenAddressFormatter;
use AIArmada\Addressing\Geography\Yemen\YemenGeographyProvider;

it('formats Yemeni addresses without a postcode system', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 1993',
        'city' => "SANA'A",
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("B.P. 1993\nSANA'A\nYemen");
});
it('prints matching Yemeni city and governorate once', function (): void {
    $formatted = app(YemenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al Wahda Street 2',
        'city' => 'Ibb',
        'state' => 'Ibb',
        'country_code' => 'YE',
    ]));

    expect($formatted)->toBe("Al Wahda Street 2\nIbb\nYemen");
});

it('ships 333 districts under governorates with parent links', function (): void {
    $areas = app(YemenGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(333)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ye:district:khamir')->name)->toBe('Khamir')
        ->and($byId->get('ye:district:crater')->name)->toBe('Crater')
        ->and($byId->get('ye:district:az-zahir')->name)->toBe('Az Zahir');
});
