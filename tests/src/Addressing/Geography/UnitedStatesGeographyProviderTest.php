<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesAddressFormatter;

it('formats American addresses with the state abbreviation and ZIP', function (): void {
    $formatted = app(UnitedStatesAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 MAGNOLIA ST',
        'city' => 'HEMPSTEAD',
        'state' => 'New York',
        'postcode' => '11550-1234',
        'country_code' => 'US',
    ]));

    expect($formatted)->toBe("123 MAGNOLIA ST\nHEMPSTEAD NY 11550-1234\nUnited States");
});
