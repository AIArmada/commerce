<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsAddressFormatter;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;

it('formats US Virgin Islander addresses as locality VI ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 Main St.',
        'city' => 'ST THOMAS',
        'postcode' => '00802-1222',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("123 Main St.\nST THOMAS VI 00802-1222\nVirgin Islands (US)");
});
it('formats Kingshill addresses with the rural route ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RR 1 BOX 6601',
        'city' => 'KINGSHILL',
        'postcode' => '00850-9802',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("RR 1 BOX 6601\nKINGSHILL VI 00850-9802\nVirgin Islands (US)");
});

it('ships 20 subdistricts under districts with parent links', function (): void {
    $areas = app(USVirginIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subdistrict');

    expect($l2)->toHaveCount(20)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('vi:subdistrict:charlotte-amalie')->name)->toBe('Charlotte Amalie')
        ->and($byId->get('vi:subdistrict:saint-thomas:east-end')->name)->toBe('East End')
        ->and($byId->get('vi:subdistrict:cruz-bay')->name)->toBe('Cruz Bay');
});
