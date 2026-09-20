<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Suriname\SurinameAddressFormatter;

it('formats Surinamese addresses without a postcode system', function (): void {
    $formatted = app(SurinameAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Walapastraat 2',
        'line2' => 'Bloemendal',
        'city' => 'PARAMARIBO',
        'country_code' => 'SR',
    ]));

    expect($formatted)->toBe("Walapastraat 2\nBloemendal\nPARAMARIBO\nSuriname");
});
it('prints any supplied Surinamese code on its own line', function (): void {
    $formatted = app(SurinameAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Walapastraat 2',
        'city' => 'PARAMARIBO',
        'postcode' => '99999',
        'country_code' => 'SR',
    ]));

    expect($formatted)->toBe("Walapastraat 2\nPARAMARIBO\n99999\nSuriname");
});
