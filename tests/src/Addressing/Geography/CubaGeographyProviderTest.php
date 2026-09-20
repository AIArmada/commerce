<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cuba\CubaAddressFormatter;

it('formats Cuban addresses with the CP postcode left of the locality', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ave Independencia s/n',
        'line2' => '19 de Mayo y Aranguren',
        'line3' => 'Habana 6',
        'city' => 'CIUDAD HABANA',
        'postcode' => 'CP 10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Ave Independencia s/n\n19 de Mayo y Aranguren\nHabana 6\nCP 10600 CIUDAD HABANA\nCuba");
});
it('formats Cuban addresses passing bare postcodes through', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle 23 No. 55',
        'city' => 'CIUDAD HABANA',
        'postcode' => '10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Calle 23 No. 55\n10600 CIUDAD HABANA\nCuba");
});
