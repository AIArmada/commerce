<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Israel\IsraelAddressFormatter;
use AIArmada\Addressing\Geography\Israel\IsraelGeographyProvider;

it('formats Israeli addresses with the 7-digit postcode left of the locality', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '16 Yafo Street',
        'city' => 'JERUSALEM',
        'postcode' => '9414219',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("16 Yafo Street\n9414219 JERUSALEM\nIsrael");
});
it('formats Israeli addresses passing legacy 5-digit codes through', function (): void {
    $formatted = app(IsraelAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Rothschild Blvd',
        'city' => 'TEL AVIV',
        'postcode' => '61201',
        'country_code' => 'IL',
    ]));

    expect($formatted)->toBe("5 Rothschild Blvd\n61201 TEL AVIV\nIsrael");
});

it('ships 15 subdistricts under districts with parent links', function (): void {
    $areas = app(IsraelGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subdistrict');

    expect($l2)->toHaveCount(15)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('il:subdistrict:golan')->name)->toBe('Golan')
        ->and($byId->get('il:subdistrict:haifa')->name)->toBe('Haifa')
        ->and($byId->get('il:subdistrict:akko')->name)->toBe('Akko');
});
