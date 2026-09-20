<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Uganda\UgandaAddressFormatter;

it('formats Ugandan addresses with the postcode left of the locality', function (): void {
    $formatted = app(UgandaAddressFormatter::class)->format(AddressData::from([
        'line1' => '22 Siad Barre Avenue',
        'city' => 'KAMPALA',
        'postcode' => '10000',
        'country_code' => 'UG',
    ]));

    expect($formatted)->toBe("22 Siad Barre Avenue\n10000 KAMPALA\nUganda");
});
