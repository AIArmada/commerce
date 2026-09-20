<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lesotho\LesothoAddressFormatter;

it('formats Basotho addresses with the postcode right of the locality', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'MASERU',
        'postcode' => '100',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMASERU 100\nLesotho");
});
it('prints matching Basotho city and district once when the postcode is missing', function (): void {
    $formatted = app(LesothoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 500',
        'city' => 'Maseru',
        'state' => 'Maseru',
        'country_code' => 'LS',
    ]));

    expect($formatted)->toBe("P.O. Box 500\nMaseru\nLesotho");
});
