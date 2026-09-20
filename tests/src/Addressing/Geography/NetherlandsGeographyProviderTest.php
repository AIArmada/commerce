<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;

it('formats Dutch addresses with two spaces after the postcode', function (): void {
    $formatted = app(NetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Drieslag 5-1',
        'city' => 'ARNHEM',
        'postcode' => '6832 am',
        'country_code' => 'NL',
    ]));

    expect($formatted)->toBe("Drieslag 5-1\n6832 AM  ARNHEM\nNetherlands");
});
