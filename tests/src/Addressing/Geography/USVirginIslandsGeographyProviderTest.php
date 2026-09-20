<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsAddressFormatter;

it('formats US Virgin Islander addresses as locality VI ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 Main St.',
        'city' => 'ST THOMAS',
        'postcode' => '00802-1222',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("123 Main St.\nST THOMAS VI 00802-1222\nVirgin Islands (US)");
});
it('formats Kingshill addresses with the rural route ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RR 1 BOX 6601',
        'city' => 'KINGSHILL',
        'postcode' => '00850-9802',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("RR 1 BOX 6601\nKINGSHILL VI 00850-9802\nVirgin Islands (US)");
});
