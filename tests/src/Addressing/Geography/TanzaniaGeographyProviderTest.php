<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaAddressFormatter;
use AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider;

it('formats Tanzanian addresses with the region on its own line', function (): void {
    $formatted = app(TanzaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Ally Hassan Mwinyi',
        'city' => 'MSASANI',
        'state' => 'DAR ES SALAM',
        'postcode' => '14111',
        'country_code' => 'TZ',
    ]));

    expect($formatted)->toBe("22 Ally Hassan Mwinyi\n14111 MSASANI\nDAR ES SALAM\nTanzania");
});

it('ships 193 districts under regions with parent links', function (): void {
    $areas = app(TanzaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(193)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tz:district:madaba')->name)->toBe('Madaba')
        ->and($byId->get('tz:district:bumbuli')->name)->toBe('Bumbuli')
        ->and($byId->get('tz:district:nanyamba-town')->name)->toBe('Nanyamba Town')
        ->and($byId->get('tz:district:zanzibar-city')->name)->toBe('Zanzibar City');
});
