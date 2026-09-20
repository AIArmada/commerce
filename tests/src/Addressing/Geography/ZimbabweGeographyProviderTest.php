<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zimbabwe\ZimbabweAddressFormatter;

it('formats Zimbabwean addresses without a postcode system', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '34–6th Crescent',
        'line2' => 'Warren Park 1',
        'city' => 'HARARE',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("34–6th Crescent\nWarren Park 1\nHARARE\nZimbabwe");
});
it('prints matching Zimbabwean city and province once', function (): void {
    $formatted = app(ZimbabweAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Josiah Tongogara Street',
        'city' => 'Bulawayo',
        'state' => 'Bulawayo',
        'country_code' => 'ZW',
    ]));

    expect($formatted)->toBe("12 Josiah Tongogara Street\nBulawayo\nZimbabwe");
});
