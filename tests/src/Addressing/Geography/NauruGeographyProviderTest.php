<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nauru\NauruAddressFormatter;
use AIArmada\Addressing\Geography\Nauru\NauruGeographyProvider;

it('prints the sole Nauru postcode on its own line after the district', function (): void {
    $formatted = app(NauruAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mr John James',
        'city' => 'BOE DISTRICT',
        'postcode' => 'NRU68',
        'country_code' => 'NR',
    ]));

    expect($formatted)->toBe("Mr John James\nBOE DISTRICT\nNRU68\nNauru");
});

it('uses the same national code for every district', function (): void {
    $formatted = app(NauruAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Civic Centre',
        'city' => 'YAREN DISTRICT',
        'postcode' => 'NRU68',
        'country_code' => 'NR',
    ]));

    expect($formatted)->toBe("Civic Centre\nYAREN DISTRICT\nNRU68\nNauru");
});

it('ships the 14 districts as terminal states', function (): void {
    $areas = app(NauruGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(14)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('nr:district:aiwo')->name)->toBe('Aiwo');
});
