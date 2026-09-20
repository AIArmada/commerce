<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CentralAfricanRepublic\CentralAfricanRepublicAddressFormatter;

it('formats Central African addresses without a postcode system', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\nCentral African Republic");
});
it('prints any supplied Central African code on its own line', function (): void {
    $formatted = app(CentralAfricanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 729',
        'city' => 'BANGUI',
        'postcode' => '99999',
        'country_code' => 'CF',
    ]));

    expect($formatted)->toBe("BP 729\nBANGUI\n99999\nCentral African Republic");
});
