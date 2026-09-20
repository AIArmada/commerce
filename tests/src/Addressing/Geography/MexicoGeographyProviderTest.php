<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mexico\MexicoAddressFormatter;

it('formats Mexican addresses with the postcode left and abbreviation after', function (): void {
    $formatted = app(MexicoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Insurgentes Sur 1602',
        'city' => 'MEXICO',
        'state' => 'Ciudad de México',
        'postcode' => '02860',
        'country_code' => 'MX',
    ]));

    expect($formatted)->toBe("Insurgentes Sur 1602\n02860 MEXICO, CDMX\nMexico");
});
