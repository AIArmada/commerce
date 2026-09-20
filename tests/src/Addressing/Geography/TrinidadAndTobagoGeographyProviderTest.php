<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoAddressFormatter;

it('formats Trinidadian addresses with the postcode right of the locality', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => '135-137 Southern Main Road',
        'city' => 'CHAGUANAS',
        'postcode' => '500234',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("135-137 Southern Main Road\nCHAGUANAS 500234\nTrinidad and Tobago");
});
it('formats Port-of-Spain addresses with the box postcode', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1872',
        'city' => 'PORT-OF-SPAIN',
        'postcode' => '150123',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("PO Box 1872\nPORT-OF-SPAIN 150123\nTrinidad and Tobago");
});
