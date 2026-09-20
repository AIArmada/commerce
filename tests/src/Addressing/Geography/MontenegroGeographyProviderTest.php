<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Montenegro\MontenegroAddressFormatter;

it('formats Montenegrin addresses with the postcode left of the locality', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Slobode br. 1',
        'city' => 'PODGORICA',
        'postcode' => '81000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Ul. Slobode br. 1\n81000 PODGORICA\nMontenegro");
});
it('formats Montenegrin coastal addresses with the town postcode', function (): void {
    $formatted = app(MontenegroAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jadranska magistrala 5',
        'city' => 'BAR',
        'postcode' => '85000',
        'country_code' => 'ME',
    ]));

    expect($formatted)->toBe("Jadranska magistrala 5\n85000 BAR\nMontenegro");
});
