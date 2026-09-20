<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Togo\TogoAddressFormatter;

it('formats Togolese addresses without a postcode system', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 526',
        'city' => 'LOME',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("B.P. 526\nLOME\nTogo");
});
it('formats Togolese addresses with the region below the locality', function (): void {
    $formatted = app(TogoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue des Jasmins',
        'city' => 'Kpalimé',
        'state' => 'Plateaux',
        'country_code' => 'TG',
    ]));

    expect($formatted)->toBe("Rue des Jasmins\nKpalimé\nPlateaux\nTogo");
});
