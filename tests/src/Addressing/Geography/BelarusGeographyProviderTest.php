<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belarus\BelarusAddressFormatter;

it('formats Belarusian addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(BelarusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'pr-t Masherova, d.1, kv.12',
        'city' => 'Minsk',
        'postcode' => '220005',
        'country_code' => 'BY',
    ]));

    expect($formatted)->toBe("pr-t Masherova, d.1, kv.12\n220005, Minsk\nBelarus");
});
it('formats Belarusian rural addresses with the oblast below the postcode line', function (): void {
    $formatted = app(BelarusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Lenina, d.3',
        'city' => 'Brest',
        'state' => 'Brest',
        'postcode' => '224000',
        'country_code' => 'BY',
    ]));

    expect($formatted)->toBe("ul. Lenina, d.3\n224000, Brest\nBelarus");
});
