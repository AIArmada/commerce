<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaAddressFormatter;
use AIArmada\Addressing\Geography\SouthAfrica\SouthAfricaGeographyProvider;

it('formats South African addresses with the locality and postcode below', function (): void {
    $formatted = app(SouthAfricaAddressFormatter::class)->format(AddressData::from([
        'line1' => '442 Thirteenth Avenue',
        'city' => 'FISH HOEK',
        'state' => 'Western Cape',
        'postcode' => '7975',
        'country_code' => 'ZA',
    ]));

    expect($formatted)->toBe("442 Thirteenth Avenue\nFISH HOEK\n7975\nSouth Africa");
});

it('ships 52 municipalities under provinces with parent links', function (): void {
    $areas = app(SouthAfricaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['district_municipality', 'city_municipality']);

    expect($l2)->toHaveCount(52)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('za:district_municipality:sarah-baartman')->name)->toBe('Sarah Baartman')
        ->and($byId->get('za:city_municipality:city-of-ekurhuleni')->name)->toBe('City of Ekurhuleni')
        ->and($byId->get('za:district_municipality:west-coast')->name)->toBe('West Coast');
});
