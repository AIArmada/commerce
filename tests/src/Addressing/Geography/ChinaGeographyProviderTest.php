<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\China\ChinaAddressFormatter;

it('formats Chinese addresses with the postcode left of the province', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.1 Jianguomenwai Avenue',
        'state' => 'BEIJING',
        'postcode' => '100004',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.1 Jianguomenwai Avenue\n100004 BEIJING\nChina");
});
it('formats Chinese addresses with the sub-province above the postcode province line', function (): void {
    $formatted = app(ChinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'No.12 Zhichun Road',
        'city' => 'Haidian District',
        'state' => 'BEIJING',
        'postcode' => '100191',
        'country_code' => 'CN',
    ]));

    expect($formatted)->toBe("No.12 Zhichun Road\nHaidian District\n100191 BEIJING\nChina");
});
