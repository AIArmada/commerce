<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jersey\JerseyAddressFormatter;

it('formats Jersey addresses with the postcode below the post town', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Town View',
        'line2' => 'Stopford Road',
        'line3' => 'St Helier',
        'city' => 'JERSEY',
        'postcode' => 'JE2 4LB',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("Town View\nStopford Road\nSt Helier\nJERSEY\nJE2 4LB\nJersey");
});
it('formats Jersey town addresses with the parish postcode', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Esplanade',
        'city' => 'St Helier',
        'postcode' => 'JE1 1AA',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("5 Esplanade\nSt Helier\nJE1 1AA\nJersey");
});
