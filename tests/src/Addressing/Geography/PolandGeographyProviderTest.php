<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;

it('formats Polish addresses with the dashed postcode left of the locality', function (): void {
    $formatted = app(PolandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Kręta 15m 10',
        'city' => 'WARSZAWA',
        'postcode' => '00-950',
        'country_code' => 'PL',
    ]));

    expect($formatted)->toBe("Ul. Kręta 15m 10\n00-950 WARSZAWA\nPoland");
});
