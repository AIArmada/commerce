<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guatemala\GuatemalaAddressFormatter;

it('formats Guatemalan addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a. Avenida "A" 10-13, zona 1',
        'line2' => 'Centro Vivo, torre 2, Apartamento 608',
        'city' => 'Guatemala',
        'postcode' => '01001',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("6a. Avenida \"A\" 10-13, zona 1\nCentro Vivo, torre 2, Apartamento 608\n01001 - Guatemala\nGuatemala");
});
it('formats Guatemalan Villa Canales addresses with the town postcode', function (): void {
    $formatted = app(GuatemalaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle Principal 1',
        'city' => 'Villa Canales',
        'postcode' => '01065',
        'country_code' => 'GT',
    ]));

    expect($formatted)->toBe("Calle Principal 1\n01065 - Villa Canales\nGuatemala");
});
