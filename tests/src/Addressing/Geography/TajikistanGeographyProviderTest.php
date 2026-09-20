<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Tajikistan\TajikistanAddressFormatter;

it('formats Tajik addresses with the postcode left of the locality', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Khoutchandi 7',
        'city' => 'GARM',
        'postcode' => '735450',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Khoutchandi 7\n735450 GARM\nTajikistan");
});
it('prints matching Tajik city and capital region once', function (): void {
    $formatted = app(TajikistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rudaki Avenue 1',
        'city' => 'DUSHANBE',
        'state' => 'Dushanbe',
        'postcode' => '734012',
        'country_code' => 'TJ',
    ]));

    expect($formatted)->toBe("Rudaki Avenue 1\n734012 DUSHANBE\nTajikistan");
});
