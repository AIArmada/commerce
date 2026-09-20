<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Chad\ChadAddressFormatter;

it('formats Chadian addresses without a postcode system', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 4148',
        'city' => 'NDJAMENA',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("BP 4148\nNDJAMENA\nChad");
});
it('formats Chadian addresses with the province below the locality', function (): void {
    $formatted = app(ChadAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenue des Martyrs',
        'city' => 'Moundou',
        'state' => 'Logone Occidental',
        'country_code' => 'TD',
    ]));

    expect($formatted)->toBe("Avenue des Martyrs\nMoundou\nLogone Occidental\nChad");
});
