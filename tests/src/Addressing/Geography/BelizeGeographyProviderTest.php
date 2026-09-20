<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belize\BelizeAddressFormatter;

it('formats Belizean addresses without a postcode system', function (): void {
    $formatted = app(BelizeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C Street  Apt 2',
        'city' => 'KINGS PARK, BELIZE CITY',
        'country_code' => 'BZ',
    ]));

    expect($formatted)->toBe("C Street  Apt 2\nKINGS PARK, BELIZE CITY\nBelize");
});
it('prints any supplied Belizean code on its own line', function (): void {
    $formatted = app(BelizeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C Street  Apt 2',
        'city' => 'BELIZE CITY',
        'postcode' => '99999',
        'country_code' => 'BZ',
    ]));

    expect($formatted)->toBe("C Street  Apt 2\nBELIZE CITY\n99999\nBelize");
});
