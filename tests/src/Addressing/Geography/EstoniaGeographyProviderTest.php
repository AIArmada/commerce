<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;

it('formats Estonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Astri 6–1',
        'city' => 'TALLINN',
        'postcode' => '11212',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Astri 6–1\n11212 TALLINN\nEstonia");
});
it('formats Estonian rural addresses with the county on the postcode line', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Allika talu',
        'line2' => 'Halliste alevik',
        'city' => 'VILJANDIMAA',
        'postcode' => '69501',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Allika talu\nHalliste alevik\n69501 VILJANDIMAA\nEstonia");
});
it('exposes corrected Estonian municipality names', function (): void {
    $areas = app(EstoniaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('ee:rural_municipality:noo')->name)->toBe('Nõo')
        ->and($areas->get('ee:rural_municipality:noo')->code)->toBe('528')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->name)->toBe('Põhja-Pärnumaa')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->code)->toBe('638')
        ->and($areas->get('ee:rural_municipality:pohja-parnumaa')->type)->toBe('rural_municipality')
        ->and($areas->get('ee:rural_municipality:poltsamaa')->name)->toBe('Põltsamaa')
        ->and($areas->get('ee:rural_municipality:joelahtme')->name)->toBe('Jõelähtme')
        ->and($areas->get('ee:rural_municipality:joelahtme')->code)->toBe('245')
        ->and($areas->has('ee:rural_municipality:pohja-parnu'))->toBeFalse();
});

it('labels tiers Maakond, Vald and Linn', function (): void {
    $provider = app(EstoniaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['county' => 'Maakond', 'rural_municipality' => 'Vald', 'urban_municipality' => 'Linn'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
