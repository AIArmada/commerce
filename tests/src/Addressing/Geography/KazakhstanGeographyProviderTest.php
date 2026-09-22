<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;

it('formats Kazakh addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Ryskulbekov, dom16, kv 224',
        'city' => 'ASTANA',
        'postcode' => 'Z00Y5M7',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("ul. Ryskulbekov, dom16, kv 224\nZ00Y5M7, ASTANA\nKazakhstan");
});
it('formats Kazakh addresses with legacy 6-digit postcodes and region', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Abay Street 1',
        'city' => 'Taldykorgan',
        'state' => 'Jetisu',
        'postcode' => '040000',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("Abay Street 1\n040000, Taldykorgan\nJetisu\nKazakhstan");
});

it('ships 170 districts under regions with parent links', function (): void {
    $areas = app(KazakhstanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(170)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('kz:district:abai')->name)->toBe('Abai')
        ->and($byId->get('kz:district:talgar')->name)->toBe('Talgar')
        ->and($byId->get('kz:district:saryagash')->name)->toBe('Saryagash');
});
