<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kuwait\KuwaitAddressFormatter;

it('formats Kuwaiti addresses with the postcode left of the locality', function (): void {
    $formatted = app(KuwaitAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Al-Sabbahiya',
        'city' => 'KUWAIT',
        'postcode' => '54551',
        'country_code' => 'KW',
    ]));

    expect($formatted)->toBe("Al-Sabbahiya\n54551 KUWAIT\nKuwait");
});
