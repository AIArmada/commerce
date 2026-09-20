<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Italy\ItalyAddressFormatter;

it('formats Italian addresses with the province abbreviation', function (): void {
    $formatted = app(ItalyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIALE EUROPA 22',
        'components' => ['province_code' => 'rm'],
        'city' => 'ROMA',
        'postcode' => '00122',
        'country_code' => 'IT',
    ]));

    expect($formatted)->toBe("VIALE EUROPA 22\n00122 ROMA RM\nItaly");
});
