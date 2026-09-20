<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Malawi\MalawiAddressFormatter;

it('formats Malawian addresses with the postcode left of the locality', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Dunduzu Avenue',
        'city' => 'KASUNGU',
        'postcode' => '102010',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("21 Dunduzu Avenue\n102010 KASUNGU\nMalawi");
});
it('formats Malawian addresses with the region below the postcode line', function (): void {
    $formatted = app(MalawiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Chipembere Highway',
        'city' => 'Blantyre',
        'state' => 'Southern',
        'postcode' => '309070',
        'country_code' => 'MW',
    ]));

    expect($formatted)->toBe("Chipembere Highway\n309070 Blantyre\nSouthern\nMalawi");
});
