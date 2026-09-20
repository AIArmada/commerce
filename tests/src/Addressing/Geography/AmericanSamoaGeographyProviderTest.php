<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaAddressFormatter;

it('formats American Samoan addresses with the US ZIP layout', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 1234',
        'city' => 'PAGO PAGO',
        'postcode' => '96799',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 1234\nPAGO PAGO AS 96799\nAmerican Samoa");
});
it('formats American Samoan ZIP+4 codes', function (): void {
    $formatted = app(AmericanSamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO BOX 999',
        'city' => 'PAGO PAGO',
        'postcode' => '96799-1234',
        'country_code' => 'AS',
    ]));

    expect($formatted)->toBe("PO BOX 999\nPAGO PAGO AS 96799-1234\nAmerican Samoa");
});
