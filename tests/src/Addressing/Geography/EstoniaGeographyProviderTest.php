<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;

it('formats Estonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Astri 6–1',
        'city' => 'TALLINN',
        'postcode' => '11212',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Astri 6–1\n11212 TALLINN\nEstonia");
});
it('formats Estonian rural addresses with the county on the postcode line', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Allika talu',
        'line2' => 'Halliste alevik',
        'city' => 'VILJANDIMAA',
        'postcode' => '69501',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Allika talu\nHalliste alevik\n69501 VILJANDIMAA\nEstonia");
});
