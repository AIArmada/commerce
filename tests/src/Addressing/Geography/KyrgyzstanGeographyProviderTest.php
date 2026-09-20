<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanAddressFormatter;

it('formats Kyrgyz addresses with the postcode left of the locality', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => '193, Avenue Chuy, apt. 28',
        'city' => 'BISHKEK',
        'postcode' => '720001',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("193, Avenue Chuy, apt. 28\n720001 BISHKEK\nKyrgyzstan");
});
it('formats rural Kyrgyz addresses with the region below the postcode line', function (): void {
    $formatted = app(KyrgyzstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lenin Street 12',
        'city' => 'KARAKOL',
        'state' => 'Issyk-Kul',
        'postcode' => '721600',
        'country_code' => 'KG',
    ]));

    expect($formatted)->toBe("Lenin Street 12\n721600 KARAKOL\nIssyk-Kul\nKyrgyzstan");
});
