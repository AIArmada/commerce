<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cuba\CubaAddressFormatter;
use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Cuban addresses with the CP postcode left of the locality', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ave Independencia s/n',
        'line2' => '19 de Mayo y Aranguren',
        'line3' => 'Habana 6',
        'city' => 'CIUDAD HABANA',
        'postcode' => 'CP 10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Ave Independencia s/n\n19 de Mayo y Aranguren\nHabana 6\nCP 10600 CIUDAD HABANA\nCuba");
});
it('formats Cuban addresses passing bare postcodes through', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle 23 No. 55',
        'city' => 'CIUDAD HABANA',
        'postcode' => '10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Calle 23 No. 55\n10600 CIUDAD HABANA\nCuba");
});

it('names the capital province La Habana with a Havana alias', function (): void {
    $areas = app(CubaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('cu:province:la-habana')->name)->toBe('La Habana')
        ->and($areas->get('cu:province:la-habana')->code)->toBe('03')
        ->and($areas->has('cu:province:havana'))->toBeFalse();

    $names = app(CubaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names['cu:province:la-habana'][0]['name'])->toBe('Havana');
});

it('ships 168 municipalitys under provinces with parent links', function (): void {
    $areas = app(CubaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(168)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('cu:municipality:santiago-de-cuba')->name)->toBe('Santiago de Cuba')
        ->and($byId->get('cu:municipality:centro-habana')->name)->toBe('Centro Habana')
        ->and($byId->get('cu:municipality:habana-del-este')->name)->toBe('Habana del Este');
});
