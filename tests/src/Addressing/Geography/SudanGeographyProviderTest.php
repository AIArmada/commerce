<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Sudan\SudanAddressFormatter;

it('formats Sudanese addresses with the postcode above the locality', function (): void {
    $formatted = app(SudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 211',
        'city' => 'KHARTOUM',
        'postcode' => '11111',
        'country_code' => 'SD',
    ]));

    expect($formatted)->toBe("B.P. 211\n11111\nKHARTOUM\nSudan");
});
