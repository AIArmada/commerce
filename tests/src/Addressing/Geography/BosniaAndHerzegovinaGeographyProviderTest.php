<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaAddressFormatter;

it('formats Bosnian addresses with the postcode left of the locality', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Semira Fraste E6/6',
        'city' => 'SARAJEVO',
        'postcode' => '71000',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Semira Fraste E6/6\n71000 SARAJEVO\nBosnia and Herzegovina");
});
it('formats Bosnian rural addresses with the numberless street line', function (): void {
    $formatted = app(BosniaAndHerzegovinaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sapna BB',
        'city' => 'SAPNA',
        'postcode' => '75411',
        'country_code' => 'BA',
    ]));

    expect($formatted)->toBe("Sapna BB\n75411 SAPNA\nBosnia and Herzegovina");
});
