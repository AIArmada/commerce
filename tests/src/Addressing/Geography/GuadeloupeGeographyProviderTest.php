<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guadeloupe\GuadeloupeAddressFormatter;

it('formats Guadeloupean addresses with the postcode left of the locality', function (): void {
    $formatted = app(GuadeloupeAddressFormatter::class)->format(AddressData::from([
        'line1' => '3 ALLEE DES ACACIAS',
        'city' => 'BASSE TERRE',
        'postcode' => '97100',
        'country_code' => 'GP',
    ]));

    expect($formatted)->toBe("3 ALLEE DES ACACIAS\n97100 BASSE TERRE\nGuadeloupe");
});
it('formats Guadeloupean Pointe-à-Pitre addresses with the town postcode', function (): void {
    $formatted = app(GuadeloupeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Frébault 8',
        'city' => 'POINTE-A-PITRE',
        'postcode' => '97110',
        'country_code' => 'GP',
    ]));

    expect($formatted)->toBe("Rue Frébault 8\n97110 POINTE-A-PITRE\nGuadeloupe");
});
