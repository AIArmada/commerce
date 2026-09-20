<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;

it('formats Gambian addresses without a postcode system', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Liberation Avenue',
        'city' => 'BANJUL',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("21 Liberation Avenue\nBANJUL\nThe Gambia");
});
it('formats Gambian addresses with the division below the locality', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Main Street',
        'city' => 'Basse',
        'state' => 'Upper River',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("Main Street\nBasse\nUpper River\nThe Gambia");
});
