<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Brazil\BrazilAddressFormatter;

it('formats Brazilian addresses with the state abbreviation and postcode below', function (): void {
    $formatted = app(BrazilAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RUA XV DE NOVEMBRO, 1751',
        'city' => 'GUARAPUAVA',
        'state' => 'Paraná',
        'postcode' => '85070-200',
        'country_code' => 'BR',
    ]));

    expect($formatted)->toBe("RUA XV DE NOVEMBRO, 1751\nGUARAPUAVA - PR\n85070-200\nBrazil");
});
