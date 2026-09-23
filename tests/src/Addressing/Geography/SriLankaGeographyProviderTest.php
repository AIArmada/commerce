<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SriLanka\SriLankaAddressFormatter;
use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;

it('formats Sri Lankan addresses with the postcode below the locality', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'line2' => 'Silkhouse Street',
        'city' => 'KANDY',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nSilkhouse Street\nKANDY\n20000\nSri Lanka");
});
it('formats Sri Lankan addresses keeping the province above the postcode line', function (): void {
    $formatted = app(SriLankaAddressFormatter::class)->format(AddressData::from([
        'line1' => '201 Shanti Villa',
        'city' => 'KANDY',
        'state' => 'Central',
        'postcode' => '20000',
        'country_code' => 'LK',
    ]));

    expect($formatted)->toBe("201 Shanti Villa\nKANDY\nCentral\n20000\nSri Lanka");
});

it('ships 25 districts under provinces with parent links', function (): void {
    $areas = app(SriLankaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'district'))->toHaveCount(25)
        ->and($byId->get('lk:district:ampara')->parentSourceId)->toBe('lk:province:eastern');
});
