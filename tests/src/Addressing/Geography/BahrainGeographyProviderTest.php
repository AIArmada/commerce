<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bahrain\BahrainAddressFormatter;

it('formats Bahraini addresses with the postcode right of the locality', function (): void {
    $formatted = app(BahrainAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House no. 888',
        'city' => 'AL-MANAMAH',
        'postcode' => '317',
        'country_code' => 'BH',
    ]));

    expect($formatted)->toBe("House no. 888\nAL-MANAMAH 317\nBahrain");
});
