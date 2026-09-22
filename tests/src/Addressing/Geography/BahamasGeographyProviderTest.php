<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bahamas\BahamasAddressFormatter;
use AIArmada\Addressing\Geography\Bahamas\BahamasGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Bahamian addresses without a postcode system', function (): void {
    $formatted = app(BahamasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box GT 2001',
        'city' => 'Nassau',
        'country_code' => 'BS',
    ]));

    expect($formatted)->toBe("P.O. Box GT 2001\nNassau\nThe Bahamas");
});
it('prints any supplied Bahamian code on its own line', function (): void {
    $formatted = app(BahamasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box N-8302',
        'city' => 'Nassau',
        'postcode' => '99999',
        'country_code' => 'BS',
    ]));

    expect($formatted)->toBe("P.O. Box N-8302\nNassau\n99999\nThe Bahamas");
});

it('uses ISO district names with aliases for the renamed districts', function (): void {
    $areas = app(BahamasGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(32)
        ->and($areas->get('bs:district:crooked-island-and-long-cay')->name)->toBe('Crooked Island and Long Cay')
        ->and($areas->get('bs:district:city-of-freeport')->name)->toBe('City of Freeport')
        ->and($areas->get('bs:district:san-salvador')->name)->toBe('San Salvador')
        ->and($areas->get('bs:island:new-providence')->type)->toBe('island')
        ->and($areas->has('bs:district:crooked-island'))->toBeFalse()
        ->and($areas->has('bs:district:freeport'))->toBeFalse()
        ->and($areas->has('bs:district:san-salvador-island'))->toBeFalse()
        ->and($areas->has('bs:district:new-providence'))->toBeFalse();

    $names = app(BahamasGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['bs:district:crooked-island-and-long-cay'][0]['name'])->toBe('Crooked Island')
        ->and($names['bs:district:city-of-freeport'][0]['name'])->toBe('Freeport')
        ->and($names['bs:district:san-salvador'][0]['name'])->toBe('San Salvador Island');
});
