<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Denmark\DenmarkAddressFormatter;
use AIArmada\Addressing\Geography\Denmark\DenmarkGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Danish addresses with the postcode left of the locality', function (): void {
    $formatted = app(DenmarkAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Kastanievej 15, 2, Agerskov',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK',
    ]));

    expect($formatted)->toBe("Kastanievej 15, 2, Agerskov\n8660 SKANDERBORG\nDenmark");
});
it('formats Danish post box addresses with the bare postcode', function (): void {
    $formatted = app(DenmarkAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Postboks 321',
        'city' => 'SKANDERBORG',
        'postcode' => '8660',
        'country_code' => 'DK',
    ]));

    expect($formatted)->toBe("Postboks 321\n8660 SKANDERBORG\nDenmark");
});

it('names region 84 Capital Region with a Hovedstaden alias', function (): void {
    $areas = app(DenmarkGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $capital = $areas->get('dk:region:capital-region');

    expect($capital->name)->toBe('Capital Region')
        ->and($capital->code)->toBe('84');

    $names = app(DenmarkGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['dk:region:capital-region'][0]['name'])->toBe('Hovedstaden');
});
