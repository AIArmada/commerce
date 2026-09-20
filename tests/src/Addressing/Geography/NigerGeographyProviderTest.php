<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Niger\NigerAddressFormatter;

it('formats Nigerien addresses with the postcode left of the locality', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NIAMEY',
        'postcode' => '8001',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8001 NIAMEY\nNiger");
});
it('formats Nigerien addresses keeping the abbreviated capital above the region', function (): void {
    $formatted = app(NigerAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 502',
        'city' => 'NY',
        'state' => 'Niamey',
        'postcode' => '8000',
        'country_code' => 'NE',
    ]));

    expect($formatted)->toBe("BP 502\n8000 NY\nNiamey\nNiger");
});
