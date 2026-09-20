<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cameroon\CameroonAddressFormatter;

it('formats Cameroonian addresses without a postcode line', function (): void {
    $formatted = app(CameroonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'B.P. 8035',
        'city' => 'YAOUNDE',
        'country_code' => 'CM',
    ]));

    expect($formatted)->toBe("B.P. 8035\nYAOUNDE\nCameroon");
});
