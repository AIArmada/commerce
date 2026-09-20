<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Niue\NiueAddressFormatter;

it('formats Niue addresses with the sole code right of the locality', function (): void {
    $formatted = app(NiueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 39',
        'city' => 'Alofi Central',
        'postcode' => '9974',
        'country_code' => 'NU',
    ]));

    expect($formatted)->toBe("PO Box 39\nAlofi Central 9974\nNiue");
});

it('uses the same island code for every village', function (): void {
    $formatted = app(NiueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Huihui Road',
        'city' => 'Alofi',
        'postcode' => '9974',
        'country_code' => 'NU',
    ]));

    expect($formatted)->toBe("Huihui Road\nAlofi 9974\nNiue");
});
