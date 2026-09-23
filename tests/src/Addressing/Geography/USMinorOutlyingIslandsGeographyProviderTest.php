<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsAddressFormatter;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsGeographyProvider;

it('formats US Minor Outlying Islands addresses without a postcode system', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wake Island Airfield',
        'city' => 'Wake Island',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("Wake Island Airfield\nWake Island\nUnited States Minor Outlying Islands");
});

it('prints any supplied Wake code on its own line', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 501',
        'city' => 'Wake Island',
        'postcode' => '96898',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("PO Box 501\nWake Island\n96898\nUnited States Minor Outlying Islands");
});

it('ships the 9 islands as terminal states', function (): void {
    $areas = app(USMinorOutlyingIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(9)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('um:island:wake-island')->name)->toBe('Wake Island');
});
