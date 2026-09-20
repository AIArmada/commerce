<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bangladesh\BangladeshAddressFormatter;

it('formats Bangladeshi addresses with spaced dash postcodes and thana', function (): void {
    $formatted = app(BangladeshAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Vil Genda',
        'components' => ['thana' => 'Savar'],
        'city' => 'DHAKA',
        'postcode' => '1340',
        'country_code' => 'BD',
    ]));

    expect($formatted)->toBe("Vil Genda\nSavar\nDHAKA - 1340\nBangladesh");
});
