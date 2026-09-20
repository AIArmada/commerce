<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belgium\BelgiumAddressFormatter;

it('formats Belgian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Volklorenlaan 81 bus 15',
        'city' => 'Wilrijk',
        'postcode' => '2610',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Volklorenlaan 81 bus 15\n2610 Wilrijk\nBelgium");
});
it('formats Belgian addresses without a province after the town', function (): void {
    $formatted = app(BelgiumAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Loi 16',
        'city' => 'Bruxelles',
        'postcode' => '1000',
        'country_code' => 'BE',
    ]));

    expect($formatted)->toBe("Rue de la Loi 16\n1000 Bruxelles\nBelgium");
});
