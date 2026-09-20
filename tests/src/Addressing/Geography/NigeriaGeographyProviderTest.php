<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Nigeria\NigeriaAddressFormatter;

it('formats Nigerian addresses with the postcode right and state below', function (): void {
    $formatted = app(NigeriaAddressFormatter::class)->format(AddressData::from([
        'line1' => '34 Alayande Cl',
        'city' => 'Mokola',
        'state' => 'OYO STATE',
        'postcode' => '200212',
        'country_code' => 'NG',
    ]));

    expect($formatted)->toBe("34 Alayande Cl\nMokola 200212\nOYO STATE\nNigeria");
});
