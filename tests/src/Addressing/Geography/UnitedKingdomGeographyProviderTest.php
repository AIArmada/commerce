<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomAddressFormatter;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;

it('formats British addresses with the post town and uppercased postcode', function (): void {
    $formatted = app(UnitedKingdomAddressFormatter::class)->format(AddressData::from([
        'line1' => '49 Featherstone Street',
        'city' => 'LONDON',
        'state' => 'Greater London',
        'postcode' => 'ec1y 8sy',
        'country_code' => 'GB',
    ]));

    expect($formatted)->toBe("49 Featherstone Street\nLONDON\nEC1Y 8SY\nUnited Kingdom");
});

it('ships 113 counties/council areas/county boroughs/districts under nations with parent links', function (): void {
    $areas = app(UnitedKingdomGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['county', 'council_area', 'county_borough', 'district']);

    expect($l2)->toHaveCount(113)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gb:county:greater-london')->name)->toBe('Greater London')
        ->and($byId->get('gb:council_area:glasgow-city')->name)->toBe('Glasgow City')
        ->and($byId->get('gb:district:antrim-and-newtownabbey')->name)->toBe('Antrim and Newtownabbey');
});
