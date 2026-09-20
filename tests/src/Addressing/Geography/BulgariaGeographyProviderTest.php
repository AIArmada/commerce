<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bulgaria\BulgariaAddressFormatter;

it('formats Bulgarian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Aleksandur Ekzarkh 2',
        'city' => 'PLOVDIV',
        'postcode' => '4000',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Aleksandur Ekzarkh 2\n4000 PLOVDIV\nBulgaria");
});
it('formats Bulgarian rural addresses with the province below the postcode line', function (): void {
    $formatted = app(BulgariaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Trifon Georgiev 1',
        'city' => 'Mechka',
        'state' => 'Ruse',
        'postcode' => '3264',
        'country_code' => 'BG',
    ]));

    expect($formatted)->toBe("ul. Trifon Georgiev 1\n3264 Mechka\nRuse\nBulgaria");
});
