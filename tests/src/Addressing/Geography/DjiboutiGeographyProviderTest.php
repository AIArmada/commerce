<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Djibouti\DjiboutiAddressFormatter;

it('formats Djiboutian addresses with the postcode left of the locality', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1663',
        'city' => 'DJIBOUTI VILLE',
        'postcode' => '77101',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 1663\n77101 DJIBOUTI VILLE\nDjibouti");
});
it('prints matching Djiboutian city and region once', function (): void {
    $formatted = app(DjiboutiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Arta',
        'state' => 'Arta',
        'postcode' => '77201',
        'country_code' => 'DJ',
    ]));

    expect($formatted)->toBe("BP 12\n77201 Arta\nDjibouti");
});
