<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Bolivia\BoliviaAddressFormatter;
use AIArmada\Addressing\Geography\Bolivia\BoliviaGeographyProvider;

it('formats Bolivian addresses without a postcode system', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CALLE AZURDUY 158',
        'city' => 'SUCRE',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("CALLE AZURDUY 158\nSUCRE\nBolivia");
});
it('formats Bolivian box addresses with the casilla in the street line', function (): void {
    $formatted = app(BoliviaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Casilla Postal 1234',
        'city' => 'LA PAZ',
        'country_code' => 'BO',
    ]));

    expect($formatted)->toBe("Casilla Postal 1234\nLA PAZ\nBolivia");
});

it('ships 112 provinces under departments with parent links', function (): void {
    $areas = app(BoliviaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'province');

    expect($l2)->toHaveCount(112)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bo:province:cercado')->name)->toBe('Cercado')
        ->and($byId->get('bo:province:andres-ibanez')->name)->toBe('Andrés Ibáñez');
});

it('labels tiers Departamento and Provincia', function (): void {
    $provider = app(BoliviaGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['department' => 'Departamento', 'province' => 'Provincia'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
