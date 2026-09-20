<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;

it('formats Qatari addresses without a postcode line', function (): void {
    $formatted = app(QatarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 3263',
        'city' => 'DOHA',
        'country_code' => 'QA',
    ]));

    expect($formatted)->toBe("P.O. Box 3263\nDOHA\nQatar");
});
