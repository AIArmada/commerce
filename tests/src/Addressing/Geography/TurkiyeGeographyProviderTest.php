<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeAddressFormatter;

it('formats Turkish addresses with the postcode left of locality and province', function (): void {
    $formatted = app(TurkiyeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Doğanbey Mah.',
        'city' => 'ULUS',
        'state' => 'ANKARA',
        'postcode' => '06101',
        'country_code' => 'TR',
    ]));

    expect($formatted)->toBe("Doğanbey Mah.\n06101 ULUS/ANKARA\nTürkiye");
});
