<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;

it('formats Laotian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'XAYSETHA',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 XAYSETHA\nLaos");
});
it('formats Laotian addresses with the province below the postcode locality line', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'Xaysetha',
        'state' => 'Vientiane',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 Xaysetha\nVientiane\nLaos");
});
