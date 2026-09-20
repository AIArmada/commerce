<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyAddressFormatter;

it('formats Barthélemois addresses with the single code left of the locality', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 RUE VICTOR HUGO',
        'city' => 'SAINT-BARTHELEMY',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("5 RUE VICTOR HUGO\n97133 SAINT-BARTHELEMY\nSaint-Barthelemy");
});
it('formats Barthélemois Gustavia addresses with the same single code', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la République 2',
        'city' => 'Gustavia',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("Rue de la République 2\n97133 Gustavia\nSaint-Barthelemy");
});
