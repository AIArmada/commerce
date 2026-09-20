<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;

it('formats Seychellois addresses without a postcode system', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'S19 - E36',
        'city' => 'Victoria',
        'state' => 'Mahé',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("S19 - E36\nVictoria\nMahé\nSeychelles");
});
it('prints any supplied Seychellois code on its own line', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1538',
        'city' => 'Victoria',
        'postcode' => '99999',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("P.O. Box 1538\nVictoria\n99999\nSeychelles");
});
