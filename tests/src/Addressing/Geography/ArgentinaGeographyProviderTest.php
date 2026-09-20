<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Argentina\ArgentinaAddressFormatter;

it('formats Argentine addresses with the CPA postcode left of the locality', function (): void {
    $formatted = app(ArgentinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TUCUMAN 1560',
        'city' => 'VILLA MARIA',
        'postcode' => 'Y5900FNF',
        'country_code' => 'AR',
    ]));

    expect($formatted)->toBe("TUCUMAN 1560\nY5900FNF VILLA MARIA\nArgentina");
});
