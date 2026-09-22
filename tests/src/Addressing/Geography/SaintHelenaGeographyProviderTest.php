<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaAddressFormatter;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;

it('formats Saint Helena addresses with the code right of the locality', function (): void {
    $formatted = app(SaintHelenaAddressFormatter::class)->format(AddressData::from([
        'line1' => '95 MARKET STREET',
        'city' => 'JAMESTOWN',
        'postcode' => 'STHL 1ZZ',
        'country_code' => 'SH',
    ]));

    expect($formatted)->toBe("95 MARKET STREET\nJAMESTOWN STHL 1ZZ\nSaint Helena");
});

it('formats Ascension addresses with their own code', function (): void {
    $formatted = app(SaintHelenaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Georgetown',
        'postcode' => 'ASCN 1ZZ',
        'country_code' => 'SH',
    ]));

    expect($formatted)->toBe("PO Box 1\nGeorgetown ASCN 1ZZ\nSaint Helena");
});

it('ships 8 districts plus Ascension and Tristan da Cunha', function (): void {
    $areas = app(SaintHelenaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(10)
        ->and($areas->get('sh:island:ascension')->code)->toBe('AC')
        ->and($areas->get('sh:island:tristan-da-cunha')->code)->toBe('TA')
        ->and($areas->get('sh:district:jamestown')->type)->toBe('district');
});
