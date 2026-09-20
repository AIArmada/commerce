<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;

it('formats Zambian addresses with the postcode right of the locality', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'KITWE',
        'postcode' => '23456',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nKITWE 23456\nZambia");
});
it('formats Zambian addresses omitting the routinely skipped postcode', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'LUSAKA',
        'state' => 'Lusaka',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nLUSAKA\nZambia");
});
