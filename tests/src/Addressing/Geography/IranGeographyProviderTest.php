<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iran\IranAddressFormatter;

it('formats Iranian addresses with the postcode below the province', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'line2' => 'No. 12 third floor',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'postcode' => '1619614153',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nNo. 12 third floor\nTehranpars\nTehran Province\n1619614153\nIran");
});
it('formats Iranian addresses without a postcode line when missing', function (): void {
    $formatted = app(IranAddressFormatter::class)->format(AddressData::from([
        'line1' => 'West 196 street',
        'city' => 'Tehranpars',
        'state' => 'Tehran Province',
        'country_code' => 'IR',
    ]));

    expect($formatted)->toBe("West 196 street\nTehranpars\nTehran Province\nIran");
});
