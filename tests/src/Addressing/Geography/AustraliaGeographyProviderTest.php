<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Australia\AustraliaAddressFormatter;

it('formats Australian addresses with double-spaced parts', function (): void {
    $formatted = app(AustraliaAddressFormatter::class)->format(AddressData::from([
        'line1' => '113 BOND ST',
        'city' => 'MELBOURNE',
        'state' => 'Victoria',
        'postcode' => '3000',
        'country_code' => 'AU',
    ]));

    expect($formatted)->toBe("113 BOND ST\nMELBOURNE  VIC  3000\nAustralia");
});
