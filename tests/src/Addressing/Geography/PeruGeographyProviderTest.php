<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Peru\PeruAddressFormatter;

it('formats Peruvian addresses with the postcode above the province', function (): void {
    $formatted = app(PeruAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jr. Jorge Salazar Araoz. N° 171',
        'state' => 'LIMA',
        'postcode' => '15074',
        'country_code' => 'PE',
    ]));

    expect($formatted)->toBe("Jr. Jorge Salazar Araoz. N° 171\n15074\nLIMA\nPeru");
});
