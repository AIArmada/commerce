<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kiribati\KiribatiAddressFormatter;

it('formats Kiribati addresses with the code right of the island', function (): void {
    $formatted = app(KiribatiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 487',
        'line2' => 'Betio',
        'city' => 'Sth Tarawa',
        'postcode' => 'KI0108',
        'country_code' => 'KI',
    ]));

    expect($formatted)->toBe("PO Box 487\nBetio\nSth Tarawa KI0108\nKiribati");
});
it('formats Line Islands addresses with their own code', function (): void {
    $formatted = app(KiribatiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 3',
        'city' => 'Kiritimati',
        'postcode' => 'KI0303',
        'country_code' => 'KI',
    ]));

    expect($formatted)->toBe("PO Box 3\nKiritimati KI0303\nKiribati");
});
