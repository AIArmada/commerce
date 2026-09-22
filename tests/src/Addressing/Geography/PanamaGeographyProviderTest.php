<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Panama\PanamaAddressFormatter;
use AIArmada\Addressing\Geography\Panama\PanamaGeographyProvider;

it('formats Panamanian addresses without a postcode system', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'VIA ESPAÑA, CALLE 6ta',
        'line2' => 'EDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("VIA ESPAÑA, CALLE 6ta\nEDIFICIO DEL PADRO No2, TERCER PISO, LOCAL No10, PARQUE LEFEVRE\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});
it('formats Panamanian box addresses keeping the zone box in the street line', function (): void {
    $formatted = app(PanamaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'APARTADO POSTAL 0832-02345',
        'city' => 'PARQUE LEFEVRE',
        'state' => 'PROVINCIA DE PANAMÁ',
        'country_code' => 'PA',
    ]));

    expect($formatted)->toBe("APARTADO POSTAL 0832-02345\nPARQUE LEFEVRE\nPROVINCIA DE PANAMÁ\nPanama");
});

it('spells the comarcas Ngäbe-Buglé and Guna Yala', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->name)->toBe('Ngäbe-Buglé Comarca')
        ->and($areas->get('pa:indigenous_region:ngabe-bugle-comarca')->code)->toBe('NB')
        ->and($areas->get('pa:indigenous_region:guna-yala')->name)->toBe('Guna Yala')
        ->and($areas->get('pa:indigenous_region:guna-yala')->code)->toBe('KY')
        ->and($areas->has('pa:indigenous_region:ngobe-bugle-comarca'))->toBeFalse()
        ->and($areas->has('pa:indigenous_region:guna'))->toBeFalse();
});

it('ships 81 districts under provinces with parent links', function (): void {
    $areas = app(PanamaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(81)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('pa:district:panama')->name)->toBe('Panamá')
        ->and($byId->get('pa:district:veraguas:santa-fe')->name)->toBe('Santa Fe')
        ->and($byId->get('pa:district:david')->name)->toBe('David');
});
