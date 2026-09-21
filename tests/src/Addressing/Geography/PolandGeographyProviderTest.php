<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Poland\PolandAddressFormatter;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Polish addresses with the dashed postcode left of the locality', function (): void {
    $formatted = app(PolandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ul. Kręta 15m 10',
        'city' => 'WARSZAWA',
        'postcode' => '00-950',
        'country_code' => 'PL',
    ]));

    expect($formatted)->toBe("Ul. Kręta 15m 10\n00-950 WARSZAWA\nPoland");
});

it('names voivodeship 16 Opole with an Opolskie alias', function (): void {
    $areas = app(PolandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $opole = $areas->get('pl:voivodeship:opole');

    expect($opole->name)->toBe('Opole')
        ->and($opole->code)->toBe('16');

    $names = app(PolandGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['pl:voivodeship:opole'][0]['name'])->toBe('Opolskie');
});
