<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsAddressFormatter;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;

it('formats Marshallese addresses with the US ZIP layout', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 175',
        'city' => 'Majuro',
        'postcode' => '96960',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 175\nMajuro MH 96960\nMarshall Islands");
});
it('formats Ebeye addresses with its own ZIP', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 7',
        'city' => 'Ebeye',
        'postcode' => '96970',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 7\nEbeye MH 96970\nMarshall Islands");
});

it('nests 24 municipalities under the 2 chains', function (): void {
    $areas = app(MarshallIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(2)
        ->and($areas->where('level', 2))->toHaveCount(24)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ralik'))->toHaveCount(14)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ratak'))->toHaveCount(10)
        ->and($byId->get('mh:municipality:majuro')->parentSourceId)->toBe('mh:chain:ratak')
        ->and($byId->get('mh:municipality:kwajalein')->parentSourceId)->toBe('mh:chain:ralik');
});
