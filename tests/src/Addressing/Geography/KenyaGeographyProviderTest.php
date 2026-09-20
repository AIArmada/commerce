<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kenya\KenyaAddressFormatter;

it('formats Kenyan addresses with the postcode below, then town', function (): void {
    $formatted = app(KenyaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P O BOX 2784 – NAKURU GPO',
        'city' => 'NAKURU',
        'state' => 'Nakuru',
        'postcode' => '20100',
        'country_code' => 'KE',
    ]));

    expect($formatted)->toBe("P O BOX 2784 – NAKURU GPO\n20100\nNAKURU\nKenya");
});
