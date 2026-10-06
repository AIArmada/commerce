<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Germany\GermanyGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 294 rural and 107 urban districts under states with parent links', function (): void {
    $areas = app(GermanyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(401)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($areas->where('type', 'rural_district'))->toHaveCount(294)
        ->and($areas->where('type', 'urban_district'))->toHaveCount(107)
        ->and($byId->get('de:district:berlin')->type)->toBe('urban_district')
        ->and($byId->get('de:district:munich')->type)->toBe('rural_district')
        ->and($byId->get('de:district:bayern:munich')->type)->toBe('urban_district')
        ->and($byId->get('de:district:aachen')->type)->toBe('rural_district');
});

it('pins the B19 endonym renames with English alternatives kept', function (): void {
    $areas = app(GermanyGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(417)
        ->and($byId->get('de:district:cleves')->name)->toBe('Kleve')
        ->and($byId->get('de:district:cologne')->name)->toBe('Köln')
        ->and($byId->get('de:district:hanover')->name)->toBe('Hannover')
        ->and($byId->get('de:district:munich')->name)->toBe('München')
        ->and($byId->get('de:district:bayern:munich')->name)->toBe('München')
        ->and($byId->get('de:district:nuremberg')->name)->toBe('Nürnberg')
        ->and($byId->get('de:district:hohenlohe')->name)->toBe('Hohenlohekreis')
        ->and($byId->get('de:district:saarpfalz')->name)->toBe('Saarpfalz-Kreis')
        ->and($byId->get('de:district:sankt-wendel')->name)->toBe('St. Wendel')
        ->and($byId->get('de:district:frankfurt-an-der-oder')->name)->toBe('Frankfurt (Oder)')
        ->and($byId->get('de:district:neustadt-aisch-bad-windsheim')->name)
        ->toBe('Neustadt an der Aisch-Bad Windsheim');
});

it('pins the B19 postal pass: 20 retargets, 4 swaps, 1 insert, S5 held', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('DE', $dir . '/germany-postal-codes.csv', $dir . '/germany-postal-code-areas.csv', 'aiarmada.addressing.germany');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(10812)
        ->and($postcodes)->toHaveCount(10911);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('29633'))->toBe('de:district:heidekreis')
        ->and($primary('33790'))->toBe('de:district:gutersloh')
        ->and($primary('35096'))->toBe('de:district:marburg-biedenkopf')
        ->and($primary('49632'))->toBe('de:district:cloppenburg')
        ->and($primary('50586'))->toBe('de:district:cologne')
        ->and($primary('55246'))->toBe('de:district:wiesbaden')
        ->and($primary('64658'))->toBe('de:district:bergstrasse')
        ->and($primary('68794'))->toBe('de:district:karlsruhe')
        ->and($primary('86692'))->toBe('de:district:donau-ries')
        ->and($primary('94405'))->toBe('de:district:dingolfing-landau')
        ->and($primary('07919'))->toBe('de:district:vogtlandkreis')
        ->and($primary('12529'))->toBe('de:district:dahme-spreewald')
        ->and($primary('21465'))->toBe('de:district:stormarn')
        ->and($primary('92637'))->toBe('de:district:weiden-in-der-oberpfalz')
        ->and($byCode->get('22113')->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['de:district:hamburg', 'de:district:stormarn'])
        ->and($primary('98711'))->toBe('de:district:ilm-kreis');
});
