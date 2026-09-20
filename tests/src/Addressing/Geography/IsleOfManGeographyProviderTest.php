<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\IsleOfMan\IsleOfManAddressFormatter;

it('formats Manx addresses with the postcode below the post town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 177',
        'city' => 'DOUGLAS',
        'postcode' => 'IM99 1PS',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("P.O. Box 177\nDOUGLAS\nIM99 1PS\nIsle of Man");
});
it('formats Manx street addresses with the sheading below the town', function (): void {
    $formatted = app(IsleOfManAddressFormatter::class)->format(AddressData::from([
        'line1' => '50 Athol Street',
        'city' => 'Douglas',
        'state' => 'Middle',
        'postcode' => 'IM1 1JB',
        'country_code' => 'IM',
    ]));

    expect($formatted)->toBe("50 Athol Street\nDouglas\nMiddle\nIM1 1JB\nIsle of Man");
});
