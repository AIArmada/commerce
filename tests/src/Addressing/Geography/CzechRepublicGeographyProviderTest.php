<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;

it('formats Czech addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hrušovská 455/10',
        'city' => 'Praha 102',
        'postcode' => '102 00',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Hrušovská 455/10\n102 00 Praha 102\nCzech Republic");
});
it('formats Czech rural addresses with the region below the postcode line', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Roprachtice 129',
        'city' => 'Roprachtice',
        'state' => 'Liberecký kraj',
        'postcode' => '513 01',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Roprachtice 129\n513 01 Roprachtice\nLiberecký kraj\nCzech Republic");
});
