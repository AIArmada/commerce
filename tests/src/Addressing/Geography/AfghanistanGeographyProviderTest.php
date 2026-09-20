<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanAddressFormatter;

it('formats Afghan addresses with the postcode left and province below', function (): void {
    $formatted = app(AfghanistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 123, Street 5',
        'city' => 'HESARAK',
        'state' => 'NANGARHAR',
        'postcode' => '265101',
        'country_code' => 'AF',
    ]));

    expect($formatted)->toBe("House No 123, Street 5\n265101 HESARAK\nNANGARHAR\nAfghanistan");
});
