<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Monaco\MonacoAddressFormatter;

it('formats Monegasque addresses with the postcode left of the locality', function (): void {
    $formatted = app(MonacoAddressFormatter::class)->format(AddressData::from([
        'line1' => '1 AVENUE DE L HERMITAGE',
        'city' => 'MONACO',
        'postcode' => '98000',
        'country_code' => 'MC',
    ]));

    expect($formatted)->toBe("1 AVENUE DE L HERMITAGE\n98000 MONACO\nMonaco");
});
it('formats Monegasque special delivery addresses with the office postcode', function (): void {
    $formatted = app(MonacoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 112',
        'city' => 'MONACO',
        'postcode' => '98001',
        'country_code' => 'MC',
    ]));

    expect($formatted)->toBe("BP 112\n98001 MONACO\nMonaco");
});
