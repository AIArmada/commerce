<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Austria\AustriaAddressFormatter;

it('formats Austrian addresses with the postcode left of the locality', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rennbahnweg 25/2/15',
        'city' => 'WIEN',
        'postcode' => '1220',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Rennbahnweg 25/2/15\n1220 WIEN\nAustria");
});
it('formats Austrian rural addresses with the state below the postcode line', function (): void {
    $formatted = app(AustriaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dorfstrasse 7',
        'city' => 'Hallstatt',
        'state' => 'Upper Austria',
        'postcode' => '4830',
        'country_code' => 'AT',
    ]));

    expect($formatted)->toBe("Dorfstrasse 7\n4830 Hallstatt\nUpper Austria\nAustria");
});
