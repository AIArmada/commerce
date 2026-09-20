<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Samoa\SamoaAddressFormatter;

it('formats Samoan addresses with the code right of the locality', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Salenesa Street Motootua',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("Salenesa Street Motootua\nApia WS1330\nSamoa");
});

it('formats Samoan PO box addresses with the district code', function (): void {
    $formatted = app(SamoaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Apia',
        'postcode' => 'WS1330',
        'country_code' => 'WS',
    ]));

    expect($formatted)->toBe("PO Box 1\nApia WS1330\nSamoa");
});
