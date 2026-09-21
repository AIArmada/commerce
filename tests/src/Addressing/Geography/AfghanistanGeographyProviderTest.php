<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;

it('formats Afghan addresses with the postcode left and province below', function (): void {
    $formatted = app(AfghanistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 123, Street 5',
        'city' => 'HESARAK',
        'state' => 'NANGARHAR',
        'postcode' => '265101',
        'country_code' => 'AF',
    ]));

    expect($formatted)->toBe("House No 123, Street 5\n265101 HESARAK\nNANGARHAR\nAfghanistan");
});

it('spells the province Uruzgan per ISO AF-URU', function (): void {
    $areas = app(AfghanistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('af:province:uruzgan')->name)->toBe('Uruzgan')
        ->and($areas->get('af:province:uruzgan')->code)->toBe('URU')
        ->and($areas->has('af:province:urozgan'))->toBeFalse();
});
