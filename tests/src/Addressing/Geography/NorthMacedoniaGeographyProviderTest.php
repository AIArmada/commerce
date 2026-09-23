<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaAddressFormatter;
use AIArmada\Addressing\Geography\NorthMacedonia\NorthMacedoniaGeographyProvider;

it('formats Macedonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(NorthMacedoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => '"Ilindenska" 2/1-8',
        'city' => 'SKOPJE',
        'postcode' => '1020',
        'country_code' => 'MK',
    ]));

    expect($formatted)->toBe("\"Ilindenska\" 2/1-8\n1020 SKOPJE\nNorth Macedonia");
});
it('formats Macedonian addresses with the town postcode', function (): void {
    $formatted = app(NorthMacedoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bulevar JNA 12',
        'city' => 'KUMANOVO',
        'postcode' => '1310',
        'country_code' => 'MK',
    ]));

    expect($formatted)->toBe("Bulevar JNA 12\n1310 KUMANOVO\nNorth Macedonia");
});

it('ships the 80 municipalities as terminal states', function (): void {
    $areas = app(NorthMacedoniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(80)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('mk:municipality:aerodrom')->name)->toBe('Aerodrom');
});

it('labels the tier Opština', function (): void {
    $provider = app(NorthMacedoniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Opština'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
