<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorAddressFormatter;

it('formats Salvadoran addresses with the postcode left of the locality', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => '6a AVENIDA NORTE 165',
        'city' => 'SAN SALVADOR',
        'postcode' => '2201',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("6a AVENIDA NORTE 165\n2201 SAN SALVADOR\nEl Salvador");
});
it('formats Salvadoran box addresses with the branch postcode', function (): void {
    $formatted = app(ElSalvadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 131',
        'line2' => 'SUCURSAL SOPAYANGO',
        'city' => 'SAN SALVADOR',
        'postcode' => '1116',
        'country_code' => 'SV',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 131\nSUCURSAL SOPAYANGO\n1116 SAN SALVADOR\nEl Salvador");
});
