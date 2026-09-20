<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kosovo\KosovoAddressFormatter;

it('formats Kosovar addresses with the postcode left of the locality', function (): void {
    $formatted = app(KosovoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rruga Lidhja e Prizrenit 10',
        'city' => 'Pristina',
        'postcode' => '10000',
        'country_code' => 'XK',
    ]));

    expect($formatted)->toBe("Rruga Lidhja e Prizrenit 10\n10000 Pristina\nKosovo");
});
it('prints matching Kosovar city and district once', function (): void {
    $formatted = app(KosovoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rruga Adem Jashari 1',
        'city' => 'Prizren',
        'state' => 'Prizren',
        'postcode' => '20000',
        'country_code' => 'XK',
    ]));

    expect($formatted)->toBe("Rruga Adem Jashari 1\n20000 Prizren\nKosovo");
});
