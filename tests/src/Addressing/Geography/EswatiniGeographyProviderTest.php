<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Eswatini\EswatiniAddressFormatter;

it('formats Eswatini addresses with the postcode below the locality', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 125',
        'city' => 'MBABANE',
        'postcode' => 'H100',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 125\nMBABANE\nH100\nEswatini");
});
it('prints matching Eswatini city and region once above the postcode', function (): void {
    $formatted = app(EswatiniAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 200',
        'city' => 'Manzini',
        'state' => 'Manzini',
        'postcode' => 'M200',
        'country_code' => 'SZ',
    ]));

    expect($formatted)->toBe("P.O. Box 200\nManzini\nM200\nEswatini");
});
