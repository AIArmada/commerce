<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Moldova\MoldovaAddressFormatter;

it('formats Moldovan addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(MoldovaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => 'MD-2012',
        'country_code' => 'MD',
    ]));

    expect($formatted)->toBe("Str. Eminescu, nr. 25/1, ap. 14\nMD-2012, CHISINAU\nMoldova");
});
it('formats Moldovan domestic addresses with a bare postcode', function (): void {
    $formatted = app(MoldovaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Str. Eminescu, nr. 25/1, ap. 14',
        'city' => 'CHISINAU',
        'postcode' => '2012',
        'country_code' => 'MD',
    ]));

    expect($formatted)->toBe("Str. Eminescu, nr. 25/1, ap. 14\n2012, CHISINAU\nMoldova");
});
