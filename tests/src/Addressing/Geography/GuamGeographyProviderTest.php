<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guam\GuamAddressFormatter;

it('formats Guamanian addresses with the US ZIP layout', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => '489 ARMY DR',
        'city' => 'BARRIGADA',
        'postcode' => '96913-9998',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("489 ARMY DR\nBARRIGADA GU 96913-9998\nGuam");
});
it('formats Hagatna addresses with its own ZIP', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Hagatna',
        'postcode' => '96910',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("PO Box 1\nHagatna GU 96910\nGuam");
});
