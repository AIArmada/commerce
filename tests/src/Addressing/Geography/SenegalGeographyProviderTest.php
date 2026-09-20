<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Senegal\SenegalAddressFormatter;

it('formats Senegalese addresses with the postcode left of the office', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVENUE CHEIKH ANTA DIOP',
        'city' => 'DAKAR',
        'postcode' => '12500',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("12 AVENUE CHEIKH ANTA DIOP\n12500 DAKAR\nSenegal");
});
it('prints matching Senegalese city and region once', function (): void {
    $formatted = app(SenegalAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1534',
        'city' => 'Ziguinchor',
        'state' => 'Ziguinchor',
        'postcode' => '27000',
        'country_code' => 'SN',
    ]));

    expect($formatted)->toBe("BP 1534\n27000 Ziguinchor\nSenegal");
});
