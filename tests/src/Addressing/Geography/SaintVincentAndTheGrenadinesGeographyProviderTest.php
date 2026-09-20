<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesAddressFormatter;

it('formats Vincentian addresses with the postcode below the locality', function (): void {
    $formatted = app(SaintVincentAndTheGrenadinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'HALIFAX STREET',
        'city' => 'KINGSTOWN',
        'postcode' => 'VC0120',
        'country_code' => 'VC',
    ]));

    expect($formatted)->toBe("HALIFAX STREET\nKINGSTOWN\nVC0120\nSaint Vincent and the Grenadines");
});
it('formats Bequia addresses with the island postcode', function (): void {
    $formatted = app(SaintVincentAndTheGrenadinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O BOX BQ400',
        'city' => 'BEQUIA',
        'postcode' => 'VC0400',
        'country_code' => 'VC',
    ]));

    expect($formatted)->toBe("P.O BOX BQ400\nBEQUIA\nVC0400\nSaint Vincent and the Grenadines");
});
