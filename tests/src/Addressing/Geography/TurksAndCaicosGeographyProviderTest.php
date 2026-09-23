<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosAddressFormatter;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;

it('formats Turks and Caicos addresses with the single code below the locality', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'George Brown Post Office',
        'line2' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'postcode' => 'TKCA 1ZZ',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("George Brown Post Office\nAirport road\nDOWNTOWN, PROVIDENCIALES\nTKCA 1ZZ\nTurks and Caicos Islands");
});
it('formats Turks and Caicos addresses without a postcode when missing', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("Airport road\nDOWNTOWN, PROVIDENCIALES\nTurks and Caicos Islands");
});

it('ships the 6 districts as terminal states', function (): void {
    $areas = app(TurksAndCaicosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(6)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('tc:district:grand-turk')->name)->toBe('Grand Turk');
});
