<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CaribbeanNetherlands\CaribbeanNetherlandsAddressFormatter;

it('formats Caribbean Netherlands addresses without a postcode system', function (): void {
    $formatted = app(CaribbeanNetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'state' => 'Bonaire',
        'country_code' => 'BQ',
    ]));

    expect($formatted)->toBe("Kaya Grandi 5\nKRALENDIJK\nBonaire\nBonaire, Sint Eustatius and Saba");
});
it('prints any supplied Caribbean Netherlands code on its own line', function (): void {
    $formatted = app(CaribbeanNetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kaya Grandi 5',
        'city' => 'KRALENDIJK',
        'postcode' => '0000 AA',
        'country_code' => 'BQ',
    ]));

    expect($formatted)->toBe("Kaya Grandi 5\nKRALENDIJK\n0000 AA\nBonaire, Sint Eustatius and Saba");
});
