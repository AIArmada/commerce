<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aruba\ArubaAddressFormatter;

it('formats Aruban addresses without a postcode system', function (): void {
    $formatted = app(ArubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sun Plaza Suite 110',
        'line2' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'country_code' => 'AW',
    ]));

    expect($formatted)->toBe("Sun Plaza Suite 110\nL.G. Smith Boulevard #160\nORANJESTAD\nAruba");
});
it('prints any supplied Aruban code on its own line', function (): void {
    $formatted = app(ArubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'postcode' => '99999',
        'country_code' => 'AW',
    ]));

    expect($formatted)->toBe("L.G. Smith Boulevard #160\nORANJESTAD\n99999\nAruba");
});
