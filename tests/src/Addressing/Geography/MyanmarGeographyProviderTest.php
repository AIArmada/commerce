<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Myanmar\MyanmarAddressFormatter;

it('formats Myanmar addresses with the locality, postcode and region', function (): void {
    $formatted = app(MyanmarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 7(A) 58 Street, Between 40 x 41',
        'city' => 'Pyigyitagon Township',
        'state' => 'Mandalay',
        'postcode' => '0505001',
        'country_code' => 'MM',
    ]));

    expect($formatted)->toBe("No. 7(A) 58 Street, Between 40 x 41\nPyigyitagon Township, 0505001\nMandalay\nMyanmar");
});
