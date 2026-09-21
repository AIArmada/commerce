<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoAddressFormatter;
use AIArmada\Addressing\Geography\TrinidadAndTobago\TrinidadAndTobagoGeographyProvider;

it('formats Trinidadian addresses with the postcode right of the locality', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => '135-137 Southern Main Road',
        'city' => 'CHAGUANAS',
        'postcode' => '500234',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("135-137 Southern Main Road\nCHAGUANAS 500234\nTrinidad and Tobago");
});
it('formats Port-of-Spain addresses with the box postcode', function (): void {
    $formatted = app(TrinidadAndTobagoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1872',
        'city' => 'PORT-OF-SPAIN',
        'postcode' => '150123',
        'country_code' => 'TT',
    ]));

    expect($formatted)->toBe("PO Box 1872\nPORT-OF-SPAIN 150123\nTrinidad and Tobago");
});

it('types Diego Martin and Siparia as boroughs and San Fernando as a city', function (): void {
    $areas = app(TrinidadAndTobagoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(15)
        ->and($areas->get('tt:borough:diego-martin')->code)->toBe('DMN')
        ->and($areas->get('tt:borough:siparia')->code)->toBe('SIP')
        ->and($areas->get('tt:city:san-fernando')->code)->toBe('SFO')
        ->and($areas->has('tt:region:diego-martin'))->toBeFalse()
        ->and($areas->has('tt:region:san-fernando'))->toBeFalse()
        ->and($areas->has('tt:region:siparia'))->toBeFalse();
});
