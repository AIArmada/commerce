<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Namibia\NamibiaAddressFormatter;

it('formats Namibian addresses with the postcode below the locality', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Private bag 13678',
        'city' => 'WINDHOEK',
        'postcode' => '10005',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("Private bag 13678\nWINDHOEK\n10005\nNamibia");
});
it('formats Namibian post box addresses with the postcode below the office', function (): void {
    $formatted = app(NamibiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 999',
        'city' => 'OKAHANDJA',
        'postcode' => '12004',
        'country_code' => 'NA',
    ]));

    expect($formatted)->toBe("PO Box 999\nOKAHANDJA\n12004\nNamibia");
});
