<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;

it('formats Somali addresses without an operational postcode', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nSomalia");
});
it('prints any supplied Somali paper code on its own line', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'postcode' => 'JH 09010',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nJH 09010\nSomalia");
});

it('ships 89 districts under regions with parent links', function (): void {
    $areas = app(SomaliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(89)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('so:district:borama')->name)->toBe('Borama')
        ->and($byId->get('so:district:berbera')->name)->toBe('Berbera')
        ->and($byId->get('so:district:kismayo')->name)->toBe('Kismayo');
});
