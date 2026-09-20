<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iceland\IcelandAddressFormatter;

it('formats Icelandic addresses with the postcode left of the locality', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Tryggvagötu 5',
        'city' => 'HAFNARFIRÐI',
        'postcode' => '220',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Tryggvagötu 5\n220 HAFNARFIRÐI\nIceland");
});
it('formats Icelandic capital addresses with the town postcode', function (): void {
    $formatted = app(IcelandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ingólfsstræti 3',
        'city' => 'REYKJAVÍK',
        'postcode' => '121',
        'country_code' => 'IS',
    ]));

    expect($formatted)->toBe("Ingólfsstræti 3\n121 REYKJAVÍK\nIceland");
});
