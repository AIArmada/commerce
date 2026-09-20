<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Taiwan\TaiwanAddressFormatter;

it('formats Taiwanese addresses with the 3+3 postcode right of the locality', function (): void {
    $formatted = app(TaiwanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No. 55, Sec. 2, Jinshan S. Rd.',
        'city' => 'Taipei City',
        'postcode' => '106409',
        'country_code' => 'TW',
    ]));

    expect($formatted)->toBe("No. 55, Sec. 2, Jinshan S. Rd.\nTaipei City 106409\nTaiwan");
});
