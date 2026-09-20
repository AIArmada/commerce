<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Mongolia\MongoliaAddressFormatter;

it('formats Mongolian addresses with the postcode right of the province', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'line2' => '15th khoroo, Bayanzurkh Duureg',
        'state' => 'ULAANBAATAR',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\n15th khoroo, Bayanzurkh Duureg\nULAANBAATAR 14560\nMongolia");
});
it('formats Mongolian addresses with the district above the postcode province line', function (): void {
    $formatted = app(MongoliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Jigjidjav street 9-11 toot',
        'city' => 'Bayanzurkh',
        'state' => 'Ulaanbaatar',
        'postcode' => '14560',
        'country_code' => 'MN',
    ]));

    expect($formatted)->toBe("Jigjidjav street 9-11 toot\nBayanzurkh\nUlaanbaatar 14560\nMongolia");
});
