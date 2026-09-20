<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\India\IndiaAddressFormatter;

it('formats Indian addresses with locality, state and postcode lines', function (): void {
    $formatted = app(IndiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '4, Amrita Shergill Road',
        'city' => 'New Delhi',
        'state' => 'Delhi',
        'postcode' => '110003',
        'country_code' => 'IN',
    ]));

    expect($formatted)->toBe("4, Amrita Shergill Road\nNew Delhi\nDelhi\n110003\nIndia");
});
