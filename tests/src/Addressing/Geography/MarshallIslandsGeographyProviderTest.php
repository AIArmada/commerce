<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsAddressFormatter;

it('formats Marshallese addresses with the US ZIP layout', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 175',
        'city' => 'Majuro',
        'postcode' => '96960',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 175\nMajuro MH 96960\nMarshall Islands");
});
it('formats Ebeye addresses with its own ZIP', function (): void {
    $formatted = app(MarshallIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 7',
        'city' => 'Ebeye',
        'postcode' => '96970',
        'country_code' => 'MH',
    ]));

    expect($formatted)->toBe("P.O. Box 7\nEbeye MH 96970\nMarshall Islands");
});
