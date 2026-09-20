<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaAddressFormatter;

it('formats Lithuanian inbound addresses with the LT postcode prefix', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės pr. 40-12',
        'city' => 'Vilnius',
        'postcode' => 'LT-04340',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės pr. 40-12\nLT-04340 Vilnius\nLithuania");
});
it('formats Lithuanian domestic addresses with a bare postcode', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės al. 60',
        'city' => 'Kaunas',
        'postcode' => '44280',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės al. 60\n44280 Kaunas\nLithuania");
});
