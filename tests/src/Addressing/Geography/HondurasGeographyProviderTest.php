<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Honduras\HondurasAddressFormatter;
use AIArmada\Addressing\Geography\Honduras\HondurasGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Honduran addresses with the 5-digit postcode left of the city', function (): void {
    $formatted = app(HondurasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Barrio El Centro, 3era Avenida',
        'city' => 'Tegucigalpa',
        'state' => 'Francisco Morazán',
        'postcode' => '11101',
        'country_code' => 'HN',
    ]));

    expect($formatted)->toBe("Barrio El Centro, 3era Avenida\n11101 Tegucigalpa\nFrancisco Morazán\nHonduras");
});
it('formats Honduran addresses passing legacy alphanumeric codes through', function (): void {
    $formatted = app(HondurasAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Barrio El Centro',
        'city' => 'LAS LAJAS',
        'state' => 'COMAYAGUA',
        'postcode' => 'CM1102',
        'country_code' => 'HN',
    ]));

    expect($formatted)->toBe("Barrio El Centro\nCM1102 LAS LAJAS\nCOMAYAGUA\nHonduras");
});

it('names the island department Islas de la Bahía with a Bay Islands alias', function (): void {
    $areas = app(HondurasGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('hn:department:islas-de-la-bahia')->name)->toBe('Islas de la Bahía')
        ->and($areas->get('hn:department:islas-de-la-bahia')->code)->toBe('IB')
        ->and($areas->has('hn:department:bay-islands'))->toBeFalse();

    $names = app(HondurasGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['hn:department:islas-de-la-bahia'][0]['name'])->toBe('Bay Islands');
});

it('ships 298 municipalitys under departments with parent links', function (): void {
    $areas = app(HondurasGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(298)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('hn:municipality:distrito-central')->name)->toBe('Distrito Central')
        ->and($byId->get('hn:municipality:san-pedro-sula')->name)->toBe('San Pedro Sula')
        ->and($byId->get('hn:municipality:la-ceiba')->name)->toBe('La Ceiba');
});
