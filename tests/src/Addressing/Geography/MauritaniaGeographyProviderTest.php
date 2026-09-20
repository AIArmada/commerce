<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaAddressFormatter;

it('formats Mauritanian addresses without a postcode system', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\nMauritania");
});
it('prints any supplied Mauritanian code on its own line', function (): void {
    $formatted = app(MauritaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 35',
        'city' => 'NOUAKCHOTT',
        'postcode' => '99999',
        'country_code' => 'MR',
    ]));

    expect($formatted)->toBe("B.P. 35\nNOUAKCHOTT\n99999\nMauritania");
});
