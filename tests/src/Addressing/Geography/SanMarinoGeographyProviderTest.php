<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoAddressFormatter;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;

it('formats Sammarinese addresses with the postcode left of the locality', function (): void {
    $formatted = app(SanMarinoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Contrada Omerelli 17',
        'city' => 'SAN MARINO',
        'postcode' => '47890',
        'country_code' => 'SM',
    ]));

    expect($formatted)->toBe("Contrada Omerelli 17\n47890 SAN MARINO\nSan Marino");
});
it('formats Sammarinese Serravalle addresses with the town postcode', function (): void {
    $formatted = app(SanMarinoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Via del Serrone 12',
        'city' => 'Serravalle',
        'postcode' => '47899',
        'country_code' => 'SM',
    ]));

    expect($formatted)->toBe("Via del Serrone 12\n47899 Serravalle\nSan Marino");
});

it('ships the 9 castelli as terminal states', function (): void {
    $areas = app(SanMarinoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(9)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('sm:municipality:borgo-maggiore')->name)->toBe('Borgo Maggiore');
});

it('labels the tier Castello', function (): void {
    $provider = app(SanMarinoGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Castello'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
