<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SouthSudan\SouthSudanAddressFormatter;

it('formats South Sudanese addresses without a postcode system', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Plot 123, Hai Malakal',
        'city' => 'JUBA',
        'state' => 'Central Equatoria',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("Plot 123, Hai Malakal\nJUBA\nCentral Equatoria\nSouth Sudan");
});
it('prints any supplied South Sudanese code on its own line', function (): void {
    $formatted = app(SouthSudanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O Box 449',
        'city' => 'JUBA',
        'postcode' => '99999',
        'country_code' => 'SS',
    ]));

    expect($formatted)->toBe("P.O Box 449\nJUBA\n99999\nSouth Sudan");
});
