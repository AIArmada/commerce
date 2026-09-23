<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaAddressFormatter;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;

it('formats Saint Lucian addresses with the postcode right of the locality', function (): void {
    $formatted = app(SaintLuciaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Block A, Apt 146',
        'line2' => 'High Street',
        'city' => 'CASTRIES',
        'postcode' => 'LC04  101',
        'country_code' => 'LC',
    ]));

    expect($formatted)->toBe("Block A, Apt 146\nHigh Street\nCASTRIES, LC04  101\nSaint Lucia");
});
it('formats Saint Lucian Choiseul addresses with the district postcode', function (): void {
    $formatted = app(SaintLuciaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Church Street',
        'city' => 'CHOISEUL',
        'postcode' => 'LC10  101',
        'country_code' => 'LC',
    ]));

    expect($formatted)->toBe("Church Street\nCHOISEUL, LC10  101\nSaint Lucia");
});

it('ships the 10 districts as terminal states', function (): void {
    $areas = app(SaintLuciaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(10)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('lc:district:castries')->name)->toBe('Castries');
});
