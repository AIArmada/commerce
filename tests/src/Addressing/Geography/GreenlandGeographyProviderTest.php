<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Greenland\GreenlandAddressFormatter;
use AIArmada\Addressing\Geography\Greenland\GreenlandGeographyProvider;

it('formats Greenlandic addresses with the code left of the locality', function (): void {
    $formatted = app(GreenlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Aqqusinersuaq 10',
        'city' => 'Nuuk',
        'postcode' => '3900',
        'country_code' => 'GL',
    ]));

    expect($formatted)->toBe("Aqqusinersuaq 10\n3900 Nuuk\nGreenland");
});

it('formats Ilulissat addresses with their own code', function (): void {
    $formatted = app(GreenlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 9',
        'city' => 'Ilulissat',
        'postcode' => '3952',
        'country_code' => 'GL',
    ]));

    expect($formatted)->toBe("PO Box 9\n3952 Ilulissat\nGreenland");
});

it('ships the 5 municipalities as terminal states', function (): void {
    $areas = app(GreenlandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(5)
        ->and($areas->pluck('parentSourceId')->filter()->isEmpty())->toBeTrue()
        ->and($byId->get('gl:municipality:sermersooq')->name)->toBe('Sermersooq');
});

it('labels the tier Kommune', function (): void {
    $provider = app(GreenlandGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['municipality' => 'Kommune'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
