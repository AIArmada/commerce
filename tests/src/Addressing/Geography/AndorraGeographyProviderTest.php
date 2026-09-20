<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Andorra\AndorraAddressFormatter;

it('formats Andorran addresses with the postcode left of the locality', function (): void {
    $formatted = app(AndorraAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 AVINGUDA TORRENT PREGO',
        'line2' => 'BP 15',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD501',
        'country_code' => 'AD',
    ]));

    expect($formatted)->toBe("12 AVINGUDA TORRENT PREGO\nBP 15\nAD501 ANDORRA LA VELLA\nAndorra");
});
it('formats Andorran street addresses with the parish postcode', function (): void {
    $formatted = app(AndorraAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avinguda Meritxell 10',
        'city' => 'ANDORRA LA VELLA',
        'postcode' => 'AD500',
        'country_code' => 'AD',
    ]));

    expect($formatted)->toBe("Avinguda Meritxell 10\nAD500 ANDORRA LA VELLA\nAndorra");
});
