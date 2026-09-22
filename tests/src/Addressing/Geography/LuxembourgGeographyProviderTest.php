<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgAddressFormatter;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;

it('formats Luxembourger addresses with the postcode left of the locality', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '71, route de Berlin',
        'city' => 'DUDELANGE',
        'postcode' => 'L-1234',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("71, route de Berlin\nL-1234 DUDELANGE\nLuxembourg");
});
it('prints matching Luxembourger city and canton once', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '2, rue de la Gare',
        'city' => 'Luxembourg',
        'state' => 'Luxembourg',
        'postcode' => 'L-1118',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("2, rue de la Gare\nL-1118 Luxembourg\nLuxembourg");
});

it('ships 100 communes under cantons with parent links', function (): void {
    $areas = app(LuxembourgGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(100)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('lu:commune:luxembourg-city')->name)->toBe('Luxembourg City')
        ->and($byId->get('lu:commune:esch-sur-alzette')->name)->toBe('Esch-sur-Alzette')
        ->and($byId->get('lu:commune:differdange')->name)->toBe('Differdange');
});
