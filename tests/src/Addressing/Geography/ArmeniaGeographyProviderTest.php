<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Armenia\ArmeniaAddressFormatter;

it('formats Armenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Saryan str 22 apt 25',
        'city' => 'YEREVAN',
        'postcode' => '0002',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Saryan str 22 apt 25\n0002 YEREVAN\nArmenia");
});
it('formats rural Armenian addresses with the region below the postcode line', function (): void {
    $formatted = app(ArmeniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Mashtots Street 1',
        'city' => 'Vedi',
        'state' => 'Ararat',
        'postcode' => '0601',
        'country_code' => 'AM',
    ]));

    expect($formatted)->toBe("Mashtots Street 1\n0601 Vedi\nArarat\nArmenia");
});
