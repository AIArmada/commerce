<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eritrea\EritreaAddressFormatter;

it('formats Eritrean addresses without a postcode system', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\nEritrea");
});
it('prints any supplied Eritrean code on its own line', function (): void {
    $formatted = app(EritreaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Awet Street 4',
        'city' => 'ASMARA',
        'postcode' => '99999',
        'country_code' => 'ER',
    ]));

    expect($formatted)->toBe("Awet Street 4\nASMARA\n99999\nEritrea");
});
