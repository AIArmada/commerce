<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\PapuaNewGuinea\PapuaNewGuineaAddressFormatter;

it('formats Papua New Guinean addresses with the code right of the locality', function (): void {
    $formatted = app(PapuaNewGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Section 20 Lot 40 Lagatoi Place',
        'city' => 'Port Moresby',
        'postcode' => '111',
        'country_code' => 'PG',
    ]));

    expect($formatted)->toBe("Section 20 Lot 40 Lagatoi Place\nPort Moresby 111\nPapua New Guinea");
});

it('formats Lae addresses with their own code', function (): void {
    $formatted = app(PapuaNewGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 555',
        'city' => 'Lae',
        'postcode' => '211',
        'country_code' => 'PG',
    ]));

    expect($formatted)->toBe("PO Box 555\nLae 211\nPapua New Guinea");
});
