<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ecuador\EcuadorAddressFormatter;
use AIArmada\Addressing\Geography\Ecuador\EcuadorGeographyProvider;

it('formats Ecuadorian addresses with the postcode and hyphen left of the locality', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Francisco Dalmau 547 y Calle 2',
        'line2' => 'Conjunto Real Audiencia, Bloque 3',
        'line3' => 'Conocoto',
        'city' => 'QUITO',
        'postcode' => '170303',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Francisco Dalmau 547 y Calle 2\nConjunto Real Audiencia, Bloque 3\nConocoto\n170303 - QUITO\nEcuador");
});
it('formats Ecuadorian Guayaquil addresses with the zone postcode', function (): void {
    $formatted = app(EcuadorAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Av. 9 de Octubre 100',
        'city' => 'GUAYAQUIL',
        'postcode' => '090306',
        'country_code' => 'EC',
    ]));

    expect($formatted)->toBe("Av. 9 de Octubre 100\n090306 - GUAYAQUIL\nEcuador");
});

it('ships 222 cantons under provinces with parent links', function (): void {
    $areas = app(EcuadorGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'canton');

    expect($l2)->toHaveCount(222)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('ec:canton:quito')->name)->toBe('Quito')
        ->and($byId->get('ec:canton:cuenca')->name)->toBe('Cuenca')
        ->and($byId->get('ec:canton:guayaquil')->name)->toBe('Guayaquil');
});

it('labels tiers Provincia and Cantón', function (): void {
    $provider = app(EcuadorGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Provincia', 'canton' => 'Cantón'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
