<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CaymanIslands\CaymanIslandsAddressFormatter;

it('formats Caymanian addresses with the postcode right of the island', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 802',
        'state' => 'Grand Cayman',
        'postcode' => 'KY1-1103',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 802\nGrand Cayman  KY1-1103\nCayman Islands");
});
it('formats Cayman Brac addresses with the Brac postcode', function (): void {
    $formatted = app(CaymanIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 12',
        'state' => 'Cayman Brac',
        'postcode' => 'KY2-2100',
        'country_code' => 'KY',
    ]));

    expect($formatted)->toBe("P.O. Box 12\nCayman Brac  KY2-2100\nCayman Islands");
});
