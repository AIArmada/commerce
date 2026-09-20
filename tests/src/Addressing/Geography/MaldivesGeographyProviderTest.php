<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Maldives\MaldivesAddressFormatter;

it('formats Maldivian addresses with the postcode right of the locality', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => '26, BODUTHAKURUFAANU MAGU',
        'city' => 'MALÉ',
        'postcode' => '20026',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("26, BODUTHAKURUFAANU MAGU\nMALÉ 20026\nMaldives");
});
it('formats Maldivian island addresses with the atoll below the postcode line', function (): void {
    $formatted = app(MaldivesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Nirolhu Magu 8',
        'city' => 'Hulhumale',
        'state' => 'Kaafu',
        'postcode' => '23000',
        'country_code' => 'MV',
    ]));

    expect($formatted)->toBe("Nirolhu Magu 8\nHulhumale 23000\nKaafu\nMaldives");
});
