<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinAddressFormatter;

it('formats Liechtenstein addresses with the postcode left of the locality', function (): void {
    $formatted = app(LiechtensteinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Städtle 37',
        'city' => 'Vaduz',
        'postcode' => '9490',
        'country_code' => 'LI',
    ]));

    expect($formatted)->toBe("Städtle 37\n9490 Vaduz\nLiechtenstein");
});
it('formats Liechtenstein addresses passing LI prefixes through', function (): void {
    $formatted = app(LiechtensteinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Poststrasse 1',
        'city' => 'Schaan',
        'postcode' => 'LI-9494',
        'country_code' => 'LI',
    ]));

    expect($formatted)->toBe("Poststrasse 1\nLI-9494 Schaan\nLiechtenstein");
});
