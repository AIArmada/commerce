<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Fiji\FijiAddressFormatter;

it('formats Fijian addresses without a postcode system', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => '14 VIRIA STREET',
        'line2' => 'VATUWAQA',
        'city' => 'SUVA',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("14 VIRIA STREET\nVATUWAQA\nSUVA\nFiji Islands");
});
it('prints any supplied Fijian code on its own line', function (): void {
    $formatted = app(FijiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 123',
        'city' => 'SUVA',
        'postcode' => '9999',
        'country_code' => 'FJ',
    ]));

    expect($formatted)->toBe("PO Box 123\nSUVA\n9999\nFiji Islands");
});
