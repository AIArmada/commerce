<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ireland\IrelandAddressFormatter;
use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;

it('formats Irish addresses with the Eircode below the county', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '56 Broomfield',
        'city' => 'MACROOM',
        'state' => 'CO. CORK',
        'postcode' => 'T37 F8HK',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("56 Broomfield\nMACROOM\nCO. CORK\nT37 F8HK\nIreland");
});
it('formats Irish Dublin addresses with the district routing key', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Grafton Street',
        'city' => 'DUBLIN 2',
        'state' => 'Dublin',
        'postcode' => 'D02 TF12',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("12 Grafton Street\nDUBLIN 2\nDublin\nD02 TF12\nIreland");
});

it('ships 26 counties under provinces with parent links', function (): void {
    $areas = app(IrelandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'county');

    expect($l2)->toHaveCount(26)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ie:county:carlow')->name)->toBe('Carlow')
        ->and($byId->get('ie:county:cavan')->parentSourceId)->toBe('ie:province:ulster');
});
