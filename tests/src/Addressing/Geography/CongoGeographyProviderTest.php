<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;

it('formats Congolese addresses without a postcode system', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\nCongo");
});
it('prints any supplied Congolese code on its own line', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'postcode' => '99999',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\n99999\nCongo");
});
