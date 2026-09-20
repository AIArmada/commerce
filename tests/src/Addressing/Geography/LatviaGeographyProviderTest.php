<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Latvia\LatviaAddressFormatter;

it('formats Latvian addresses with the postcode right of the locality', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kr. Barona street 7, dz. 1',
        'city' => 'RIGA',
        'postcode' => 'LV-1050',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Kr. Barona street 7, dz. 1\nRIGA, LV-1050\nLatvia");
});
it('formats Latvian sub-locality addresses with the office postcode', function (): void {
    $formatted = app(LatviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Valdemāra street 42, Ainaži',
        'city' => 'SALACGRIVAS NOV.',
        'postcode' => 'LV-4035',
        'country_code' => 'LV',
    ]));

    expect($formatted)->toBe("Valdemāra street 42, Ainaži\nSALACGRIVAS NOV., LV-4035\nLatvia");
});
