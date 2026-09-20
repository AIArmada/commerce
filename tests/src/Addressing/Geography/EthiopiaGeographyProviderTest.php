<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ethiopia\EthiopiaAddressFormatter;

it('formats Ethiopian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EthiopiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1519',
        'city' => 'ADDIS ABABA',
        'postcode' => '1000',
        'country_code' => 'ET',
    ]));

    expect($formatted)->toBe("P.O. Box 1519\n1000 ADDIS ABABA\nEthiopia");
});
