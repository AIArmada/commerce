<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Palestine\PalestineAddressFormatter;

it('formats Palestinian addresses with the P postcode right of the locality', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Irsal Street 10',
        'city' => 'RAMALLAH AND AL-BIREH',
        'postcode' => 'P6100154',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Irsal Street 10\nRAMALLAH AND AL-BIREH P6100154\nPalestine");
});
it('formats Palestinian addresses passing short P codes through', function (): void {
    $formatted = app(PalestineAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Dahiyat al Bareed',
        'city' => 'JERUSALEM',
        'postcode' => 'P126',
        'country_code' => 'PS',
    ]));

    expect($formatted)->toBe("Dahiyat al Bareed\nJERUSALEM P126\nPalestine");
});
