<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Germany\GermanyAddressFormatter;

it('formats German addresses with the postcode left of the locality', function (): void {
    $formatted = app(GermanyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wacholderweg 52a',
        'city' => 'OLDENBURG',
        'postcode' => '26133',
        'country_code' => 'DE',
    ]));

    expect($formatted)->toBe("Wacholderweg 52a\n26133 OLDENBURG\nGermany");
});
