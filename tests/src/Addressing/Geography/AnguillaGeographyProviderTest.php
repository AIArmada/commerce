<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Anguilla\AnguillaAddressFormatter;

it('formats Anguillan addresses with the single code below the locality', function (): void {
    $formatted = app(AnguillaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'postcode' => 'AI-2640',
        'country_code' => 'AI',
    ]));

    expect($formatted)->toBe("P.O. Box 60\nThe Valley\nAI-2640\nAnguilla");
});
it('formats Anguillan addresses without a postcode when missing', function (): void {
    $formatted = app(AnguillaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 60',
        'city' => 'The Valley',
        'country_code' => 'AI',
    ]));

    expect($formatted)->toBe("P.O. Box 60\nThe Valley\nAnguilla");
});
