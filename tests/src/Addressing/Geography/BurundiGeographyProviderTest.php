<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;

it('formats Burundian addresses without a postcode system', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'state' => 'Bujumbura',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\nBujumbura\nBurundi");
});
it('prints any supplied Burundian code on its own line', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'postcode' => '99999',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\n99999\nBurundi");
});
