<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Morocco\MoroccoAddressFormatter;

it('formats Moroccan addresses with the postcode left of the locality', function (): void {
    $formatted = app(MoroccoAddressFormatter::class)->format(AddressData::from([
        'line1' => '23 BOULEVARD TAROUDANT',
        'city' => 'ERRACHIDIA',
        'postcode' => '52000',
        'country_code' => 'MA',
    ]));

    expect($formatted)->toBe("23 BOULEVARD TAROUDANT\n52000 ERRACHIDIA\nMorocco");
});
