<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Madagascar\MadagascarAddressFormatter;

it('formats Malagasy addresses with the postcode left of the town', function (): void {
    $formatted = app(MadagascarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot II M 85 D Antsahameva',
        'city' => 'TOAMASINA',
        'postcode' => '501',
        'country_code' => 'MG',
    ]));

    expect($formatted)->toBe("Lot II M 85 D Antsahameva\n501 TOAMASINA\nMadagascar");
});
