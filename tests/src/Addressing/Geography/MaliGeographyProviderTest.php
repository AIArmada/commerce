<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mali\MaliAddressFormatter;

it('formats Malian addresses without a postcode system', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 406 – porte 39',
        'line2' => 'Magnabougou',
        'city' => 'BAMAKO',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 406 – porte 39\nMagnabougou\nBAMAKO\nMali");
});
it('prints matching Malian city and region once', function (): void {
    $formatted = app(MaliAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue 10',
        'city' => 'Sikasso',
        'state' => 'Sikasso',
        'country_code' => 'ML',
    ]));

    expect($formatted)->toBe("Rue 10\nSikasso\nMali");
});
