<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bahamas\BahamasAddressFormatter;

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
