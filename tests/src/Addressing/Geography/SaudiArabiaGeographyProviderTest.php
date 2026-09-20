<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaudiArabia\SaudiArabiaAddressFormatter;

it('formats Saudi addresses with the postcode above the locality', function (): void {
    $formatted = app(SaudiArabiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '2929 Rayhanah Bint Zaid',
        'city' => 'RIYADH',
        'postcode' => '13337',
        'country_code' => 'SA',
    ]));

    expect($formatted)->toBe("2929 Rayhanah Bint Zaid\n13337\nRIYADH\nSaudi Arabia");
});
