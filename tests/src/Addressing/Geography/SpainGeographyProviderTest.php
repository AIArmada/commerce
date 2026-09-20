<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Spain\SpainAddressFormatter;

it('formats Spanish addresses with the province on its own line', function (): void {
    $formatted = app(SpainAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Huertas 18, 4º, C',
        'city' => 'MARBELLA',
        'state' => 'MÁLAGA',
        'postcode' => '29400',
        'country_code' => 'ES',
    ]));

    expect($formatted)->toBe("Calle Huertas 18, 4º, C\n29400 MARBELLA\nMÁLAGA\nSpain");
});
