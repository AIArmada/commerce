<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\AntiguaAndBarbuda\AntiguaAndBarbudaAddressFormatter;

it('formats Antiguan addresses without a postcode system', function (): void {
    $formatted = app(AntiguaAndBarbudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'country_code' => 'AG',
    ]));

    expect($formatted)->toBe("P.O. Box 123\nST. JOHN'S\nAntigua and Barbuda");
});
it('prints any supplied Antiguan code on its own line', function (): void {
    $formatted = app(AntiguaAndBarbudaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 123',
        'city' => "ST. JOHN'S",
        'postcode' => '99999',
        'country_code' => 'AG',
    ]));

    expect($formatted)->toBe("P.O. Box 123\nST. JOHN'S\n99999\nAntigua and Barbuda");
});
