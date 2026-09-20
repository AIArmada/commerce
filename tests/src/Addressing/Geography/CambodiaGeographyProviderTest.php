<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cambodia\CambodiaAddressFormatter;

it('formats Cambodian addresses with the postcode right of the province', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'state' => 'PHNOM PENH',
        'postcode' => '120209',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nPHNOM PENH 120209\nCambodia");
});
it('formats Cambodian addresses with the city above the postcode province line', function (): void {
    $formatted = app(CambodiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '100E0 Street 118',
        'city' => 'Krong Siem Reap',
        'state' => 'Siem Reap',
        'postcode' => '17000',
        'country_code' => 'KH',
    ]));

    expect($formatted)->toBe("100E0 Street 118\nKrong Siem Reap\nSiem Reap 17000\nCambodia");
});
