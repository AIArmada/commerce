<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;

it('formats Gabonese addresses with the zone left of the locality', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 13210',
        'city' => 'LIBREVILLE',
        'postcode' => '01',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 13210\n01 LIBREVILLE\nGabon");
});
it('formats Gabonese addresses with the province below the postcode line', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 45',
        'city' => 'TCHIBANGA',
        'state' => 'Nyanga',
        'postcode' => '05',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 45\n05 TCHIBANGA\nNyanga\nGabon");
});
