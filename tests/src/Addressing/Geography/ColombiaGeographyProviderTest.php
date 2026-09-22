<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Colombia\ColombiaAddressFormatter;
use AIArmada\Addressing\Geography\Colombia\ColombiaGeographyProvider;

it('formats Colombian addresses with the postcode right and department below', function (): void {
    $formatted = app(ColombiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'CARRERA 7 NO. 27-18',
        'city' => 'PLANETA RICA',
        'state' => 'CORDOBA',
        'postcode' => '233057',
        'country_code' => 'CO',
    ]));

    expect($formatted)->toBe("CARRERA 7 NO. 27-18\nPLANETA RICA 233057\nCORDOBA\nColombia");
});

it('ships 1140 municipalities/localities/areas under departments with parent links', function (): void {
    $areas = app(ColombiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->whereIn('type', ['municipality', 'locality', 'non_municipalized_area']);

    expect($l2)->toHaveCount(1140)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('co:municipality:medellin')->name)->toBe('Medellín')
        ->and($byId->get('co:locality:suba')->name)->toBe('Suba')
        ->and($byId->get('co:municipality:cali')->name)->toBe('Cali');
});
