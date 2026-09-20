<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaAddressFormatter;

it('formats North Korean addresses without a postcode system', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\nNorth Korea");
});
it('prints any supplied North Korean code on its own line', function (): void {
    $formatted = app(NorthKoreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Quartier Bottongang',
        'city' => 'PYONGYANG',
        'postcode' => '999999',
        'country_code' => 'KP',
    ]));

    expect($formatted)->toBe("Quartier Bottongang\nPYONGYANG\n999999\nNorth Korea");
});
