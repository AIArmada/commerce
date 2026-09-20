<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tuvalu\TuvaluAddressFormatter;

it('formats Tuvaluan addresses without a postcode system', function (): void {
    $formatted = app(TuvaluAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'country_code' => 'TV',
    ]));

    expect($formatted)->toBe("PO Box 1\nFunafuti\nTuvalu");
});

it('prints any supplied Funafuti code on its own line', function (): void {
    $formatted = app(TuvaluAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Funafuti',
        'postcode' => '99999',
        'country_code' => 'TV',
    ]));

    expect($formatted)->toBe("PO Box 1\nFunafuti\n99999\nTuvalu");
});
