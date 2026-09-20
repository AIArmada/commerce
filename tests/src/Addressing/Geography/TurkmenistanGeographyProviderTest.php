<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Turkmenistan\TurkmenistanAddressFormatter;

it('formats Turkmen addresses with the postcode below the locality', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'j. 19 otag 1',
        'line2' => 'kv. 122',
        'city' => 'BALKANABAT',
        'state' => 'Balkan',
        'postcode' => '745100',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("j. 19 otag 1\nkv. 122\nBALKANABAT\nBalkan\n745100\nTurkmenistan");
});
it('prints matching Turkmen city and capital once above the postcode', function (): void {
    $formatted = app(TurkmenistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Galkynysh Street 1',
        'city' => 'ASHGABAT',
        'state' => 'Ashgabat',
        'postcode' => '744000',
        'country_code' => 'TM',
    ]));

    expect($formatted)->toBe("Galkynysh Street 1\nASHGABAT\n744000\nTurkmenistan");
});
