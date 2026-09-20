<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewZealand\NewZealandAddressFormatter;

it('formats New Zealand addresses with the code left of the locality', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => '100 Queen Street',
        'city' => 'Auckland',
        'postcode' => '1010',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("100 Queen Street\n1010 Auckland\nNew Zealand");
});

it('formats Wellington addresses with their own code', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Wellington',
        'postcode' => '6011',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("PO Box 1\n6011 Wellington\nNew Zealand");
});
