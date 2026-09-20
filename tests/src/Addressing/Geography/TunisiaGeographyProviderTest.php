<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tunisia\TunisiaAddressFormatter;

it('formats Tunisian addresses with the postcode left of the locality', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'BOU SALEM',
        'postcode' => '8170',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n8170 BOU SALEM\nTunisia");
});
it('prints matching Tunisian city and governorate once', function (): void {
    $formatted = app(TunisiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 AVENUE BOURGUIBA',
        'city' => 'TUNIS',
        'state' => 'Tunis',
        'postcode' => '1002',
        'country_code' => 'TN',
    ]));

    expect($formatted)->toBe("15 AVENUE BOURGUIBA\n1002 TUNIS\nTunisia");
});
