<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cyprus\CyprusAddressFormatter;

it('formats Cypriot inbound addresses with the CY postcode prefix', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sofochleous 26',
        'city' => 'Strovolos',
        'postcode' => 'CY-2008',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Sofochleous 26\nCY-2008 Strovolos\nCyprus");
});
it('formats Cypriot domestic addresses with a bare postcode', function (): void {
    $formatted = app(CyprusAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Griva Digeni 10',
        'city' => 'Larnaka',
        'postcode' => '6036',
        'country_code' => 'CY',
    ]));

    expect($formatted)->toBe("Griva Digeni 10\n6036 Larnaka\nCyprus");
});
