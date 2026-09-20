<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Iraq\IraqAddressFormatter;

it('formats Iraqi addresses with city, governorate and postcode below', function (): void {
    $formatted = app(IraqAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hay AL Asmaee, Zukak 2',
        'city' => 'AL ASMAEE',
        'state' => 'AL BASRAH',
        'postcode' => '61002',
        'country_code' => 'IQ',
    ]));

    expect($formatted)->toBe("Hay AL Asmaee, Zukak 2\nAL ASMAEE, AL BASRAH\n61002\nIraq");
});
