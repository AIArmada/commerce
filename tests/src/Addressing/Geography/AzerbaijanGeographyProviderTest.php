<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanAddressFormatter;

it('formats Azerbaijani addresses with the AZ postcode left of the locality', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Zrifliyeva küç., ev 9',
        'city' => 'Bakı',
        'postcode' => 'AZ1010',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("Zrifliyeva küç., ev 9\nAZ1010 Bakı\nAzerbaijan");
});
it('formats rural Azerbaijani addresses with the region below the postcode line', function (): void {
    $formatted = app(AzerbaijanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'H. Aliyev küç. 3',
        'city' => 'Nehrəm',
        'state' => 'Nakhchivan',
        'postcode' => 'AZ6715',
        'country_code' => 'AZ',
    ]));

    expect($formatted)->toBe("H. Aliyev küç. 3\nAZ6715 Nehrəm\nNakhchivan\nAzerbaijan");
});
