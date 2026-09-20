<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchGuiana\FrenchGuianaAddressFormatter;

it('formats Guianese addresses with the postcode left of the locality', function (): void {
    $formatted = app(FrenchGuianaAddressFormatter::class)->format(AddressData::from([
        'line1' => '3 AVENUE HENRI AGARANDE',
        'city' => 'CAYENNE',
        'postcode' => '97300',
        'country_code' => 'GF',
    ]));

    expect($formatted)->toBe("3 AVENUE HENRI AGARANDE\n97300 CAYENNE\nFrench Guiana");
});
it('formats Guianese Kourou addresses with the town postcode', function (): void {
    $formatted = app(FrenchGuianaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue des Roches 1',
        'city' => 'KOUROU',
        'postcode' => '97310',
        'country_code' => 'GF',
    ]));

    expect($formatted)->toBe("Avenue des Roches 1\n97310 KOUROU\nFrench Guiana");
});
