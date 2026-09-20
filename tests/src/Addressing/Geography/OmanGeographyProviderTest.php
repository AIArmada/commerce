<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Oman\OmanAddressFormatter;

it('formats Omani addresses with the postcode above the locality', function (): void {
    $formatted = app(OmanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 15',
        'city' => 'AL-KHOER',
        'postcode' => '133',
        'country_code' => 'OM',
    ]));

    expect($formatted)->toBe("P.O. Box 15\n133\nAL-KHOER\nOman");
});
