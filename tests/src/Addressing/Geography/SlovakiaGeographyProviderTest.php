<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaAddressFormatter;

it('formats Slovak addresses with the spaced postcode left of the office', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lúčna 1157/13',
        'city' => 'TRNAVA',
        'postcode' => '917 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Lúčna 1157/13\n917 01 TRNAVA\nSlovakia");
});
it('formats Slovak Žilina addresses with the town postcode', function (): void {
    $formatted = app(SlovakiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Národná 5',
        'city' => 'Žilina',
        'postcode' => '010 01',
        'country_code' => 'SK',
    ]));

    expect($formatted)->toBe("Národná 5\n010 01 Žilina\nSlovakia");
});
