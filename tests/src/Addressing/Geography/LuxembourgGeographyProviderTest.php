<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgAddressFormatter;

it('formats Luxembourger addresses with the postcode left of the locality', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '71, route de Berlin',
        'city' => 'DUDELANGE',
        'postcode' => 'L-1234',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("71, route de Berlin\nL-1234 DUDELANGE\nLuxembourg");
});
it('prints matching Luxembourger city and canton once', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '2, rue de la Gare',
        'city' => 'Luxembourg',
        'state' => 'Luxembourg',
        'postcode' => 'L-1118',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("2, rue de la Gare\nL-1118 Luxembourg\nLuxembourg");
});
