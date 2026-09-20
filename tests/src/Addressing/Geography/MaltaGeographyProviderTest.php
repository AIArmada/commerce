<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Malta\MaltaAddressFormatter;

it('formats Maltese addresses with the postcode below the locality', function (): void {
    $formatted = app(MaltaAddressFormatter::class)->format(AddressData::from([
        'line1' => '38 Triq it-Tempji Neolitici',
        'city' => 'IL-HAMRUN',
        'postcode' => 'HMR 1428',
        'country_code' => 'MT',
    ]));

    expect($formatted)->toBe("38 Triq it-Tempji Neolitici\nIL-HAMRUN\nHMR 1428\nMalta");
});
it('formats Maltese Valletta addresses with the locality postcode', function (): void {
    $formatted = app(MaltaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Palace Square 1',
        'city' => 'VALLETTA',
        'postcode' => 'VLT 1117',
        'country_code' => 'MT',
    ]));

    expect($formatted)->toBe("Palace Square 1\nVALLETTA\nVLT 1117\nMalta");
});
