<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 16 post-2011 first-level areas with ISO 3166-2:CU codes', function (): void {
    $areas = app(CubaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1)->toHaveCount(16)
        ->and($l1->where('type', 'province'))->toHaveCount(15)
        ->and($l1->where('type', 'special_municipality'))->toHaveCount(1)
        ->and($l1->map->code->sort()->values()->all())->toBe(['01', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '99'])
        ->and($byId->get('cu:province:la-habana')->code)->toBe('03')
        ->and($byId->get('cu:special_municipality:isla-de-la-juventud')->code)->toBe('99');
});

it('ships 168 municipalities with the B15 renames and keeps', function (): void {
    $areas = app(CubaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(168)
        // B15 renames (COD-AB + eswiki + Mapanet names).
        ->and($byId->get('cu:municipality:la-habana-vieja')->name)->toBe('La Habana Vieja')
        ->and($byId->has('cu:municipality:old-havana'))->toBeFalse()
        ->and($byId->get('cu:municipality:songo-la-maya')->name)->toBe('Songo-La Maya')
        // B15 keeps (ties held).
        ->and($byId->get('cu:municipality:habana-del-este')->name)->toBe('Habana del Este')
        ->and($byId->get('cu:municipality:minas-de-matahambre')->name)->toBe('Minas de Matahambre');
});

it('links 772 codes with the B15 corrupt drops, moves, duals and fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CU', $dir . '/cuba-postal-codes.csv', $dir . '/cuba-postal-code-areas.csv', 'aiarmada.addressing.cuba');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(777);

    $byCode = $postcodes->groupBy->code;

    // Corrupt rows dropped (shape-broken, zero-garbled, ghost 20600).
    foreach (['-', '-8104', '000 3', '2', '7', '00008', '00035', '00095', '20600'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    // Moves (Mapanet + usage / directories).
    expect($byCode->get('10200')->map->areaSourceId->all())->toBe(['cu:municipality:centro-habana'])
        ->and($byCode->get('99420')->map->areaSourceId->all())->toBe(['cu:municipality:yateras'])
        // Duals (boundary zones, majority primary).
        ->and($byCode->get('10600')->map->areaSourceId->sort()->values()->all())
        ->toBe(['cu:municipality:cerro', 'cu:municipality:plaza-de-la-revolucion'])
        ->and($byCode->get('10600')->where('isPrimary', true)->first()->areaSourceId)->toBe('cu:municipality:cerro')
        ->and($byCode->get('11400')->map->areaSourceId->sort()->values()->all())
        ->toBe(['cu:municipality:marianao', 'cu:municipality:playa'])
        ->and($byCode->get('11400')->where('isPrimary', true)->first()->areaSourceId)->toBe('cu:municipality:playa')
        // Fills (Mapanet + OSM, usage + OSM).
        ->and($byCode->get('22600')->first()->areaSourceId)->toBe('cu:municipality:la-palma')
        ->and($byCode->get('53310')->first()->areaSourceId)->toBe('cu:municipality:sagua-la-grande')
        ->and($byCode->get('73200')->first()->areaSourceId)->toBe('cu:municipality:santa-cruz-del-sur')
        ->and($byCode->get('97310')->first()->areaSourceId)->toBe('cu:municipality:baracoa')
        ->and($byCode->get('19120')->first()->areaSourceId)->toBe('cu:municipality:habana-del-este')
        // Keeps (operator lineage over stale/noisy challengers).
        ->and($byCode->get('10100')->first()->areaSourceId)->toBe('cu:municipality:la-habana-vieja')
        ->and($byCode->get('10500')->first()->areaSourceId)->toBe('cu:municipality:diez-de-octubre')
        ->and($byCode->get('10900')->first()->areaSourceId)->toBe('cu:municipality:arroyo-naranjo')
        ->and($byCode->get('52310')->first()->areaSourceId)->toBe('cu:municipality:sagua-la-grande')
        ->and($byCode->get('35100')->first()->areaSourceId)->toBe('cu:municipality:artemisa');
});
