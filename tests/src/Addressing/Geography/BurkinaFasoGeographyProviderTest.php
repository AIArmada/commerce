<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('nests all 47 provinces at level 2 under their regions', function (): void {
    $areas = app(BurkinaFasoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(64);

    $provinces = $areas->where('type', 'province');
    $regions = $areas->where('type', 'region')->keyBy('sourceId');

    expect($provinces)->toHaveCount(47)
        ->and($regions)->toHaveCount(17)
        ->and($provinces->every(fn ($area) => $area->level === 2 && $regions->has($area->parentSourceId)))->toBeTrue();

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('bf:province:koosin')->parentSourceId)->toBe('bf:region:sourou')
        ->and($byId->get('bf:province:dyamongou')->parentSourceId)->toBe('bf:region:tapoa')
        ->and($byId->get('bf:province:gobnangou')->parentSourceId)->toBe('bf:region:tapoa')
        ->and($byId->get('bf:province:karo-peli')->parentSourceId)->toBe('bf:region:soum')
        ->and($byId->get('bf:province:djelgodji')->parentSourceId)->toBe('bf:region:soum');
});

it('exposes the province level below the region level', function (): void {
    $levels = app(BurkinaFasoGeographyProvider::class)->addressHierarchies()[0]->levels;

    expect($levels)->toHaveCount(2)
        ->and($levels[0]->key)->toBe('region')
        ->and($levels[0]->kind)->toBe('state')
        ->and($levels[1]->key)->toBe('province')
        ->and($levels[1]->parentKey)->toBe('region');
});

it('ships the post-reform composition with the Boulkiemdé accent fix', function (): void {
    $areas = app(BurkinaFasoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    // Region -> province counts per the COD-AB Apr-2026 admin1/admin2 table.
    $expected = [
        'bf:region:bankui' => 3, 'bf:region:djoro' => 4, 'bf:region:goulmou' => 2,
        'bf:region:guiriko' => 3, 'bf:region:kadiogo' => 1, 'bf:region:kuilse' => 3,
        'bf:region:liptako' => 3, 'bf:region:nakambe' => 3, 'bf:region:nando' => 4,
        'bf:region:nazinon' => 3, 'bf:region:oubri' => 3, 'bf:region:sirba' => 2,
        'bf:region:soum' => 2, 'bf:region:sourou' => 3, 'bf:region:tannounyan' => 2,
        'bf:region:tapoa' => 2, 'bf:region:yaadga' => 4,
    ];

    expect($l2)->toHaveCount(47);

    foreach ($expected as $region => $count) {
        expect($l2->where('parentSourceId', $region))->toHaveCount($count, $region);
    }

    // M3 accent fix: ISO 3166-2:BF + COD-AB + province article all read Boulkiemdé.
    expect($byId->get('bf:province:boulkiemde')->name)->toBe('Boulkiemdé')
        ->and($byId->get('bf:province:boulkiemde')->code)->toBe('BLK')
        ->and($byId->get('bf:province:boulkiemde')->parentSourceId)->toBe('bf:region:nando');
});

it('links the 467 La Poste finder codes to their provinces', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BF', $dir . '/burkina-faso-postal-codes.csv', $dir . '/burkina-faso-postal-code-areas.csv', 'aiarmada.addressing.burkina_faso');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(467)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(467)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(467)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // UPU BFA profile examples + finder-verified split-province and homonym anchors.
    expect($primary('10000'))->toBe('bf:province:kadiogo') // OUAGADOUGOU commune
        ->and($primary('10010'))->toBe('bf:province:kadiogo') // CISSIN quartier
        ->and($primary('70000'))->toBe('bf:province:boulgou') // TENKODOGO
        ->and($primary('90200'))->toBe('bf:province:houet') // BAMA commune
        ->and($primary('91001'))->toBe('bf:province:kenedougou') // ORODARA agence (UPU 91001 BAMA is illustrative)
        ->and($primary('31000'))->toBe('bf:province:bazega') // KOMBISSIRI
        ->and($primary('62200'))->toBe('bf:province:karo-peli') // ARBINDA
        ->and($primary('62000'))->toBe('bf:province:djelgodji') // DJIBO
        ->and($primary('79250'))->toBe('bf:province:dyamongou') // KANTCHARI
        ->and($primary('79000'))->toBe('bf:province:gobnangou') // DIAPAGA
        ->and($primary('70500'))->toBe('bf:province:boulgou') // BOUSSOUMA (Boulgou homonym)
        ->and($primary('50250'))->toBe('bf:province:sandbondtenga') // BOUSSOUMA (Sanmatenga homonym)
        ->and($primary('70550'))->toBe('bf:province:boulgou') // CINKANSE postal locality
        ->and($primary('42350'))->toBe('bf:province:sissili'); // NIAMBOURI (= Niabouri)
});

it('keeps every code inside its province prefix block', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $rows = array_map(str_getcsv(...), file($dir . '/burkina-faso-postal-code-areas.csv'));
    array_shift($rows);

    // Split provinces share their parent block (62 = ex-Soum, 79 = ex-Tapoa).
    $blocks = [
        '10' => ['bf:province:kadiogo'], '20' => ['bf:province:bassitenga'],
        '21' => ['bf:province:ganzourgou'], '22' => ['bf:province:kourweogo'],
        '30' => ['bf:province:zoundweogo'], '31' => ['bf:province:bazega'],
        '32' => ['bf:province:nahouri'], '40' => ['bf:province:boulkiemde'],
        '41' => ['bf:province:sanguie'], '42' => ['bf:province:sissili'],
        '43' => ['bf:province:ziro'], '50' => ['bf:province:sandbondtenga'],
        '51' => ['bf:province:bam'], '52' => ['bf:province:namentenga'],
        '55' => ['bf:province:yatenga'], '56' => ['bf:province:loroum'],
        '57' => ['bf:province:passore'], '58' => ['bf:province:zondoma'],
        '60' => ['bf:province:seno'], '61' => ['bf:province:oudalan'],
        '62' => ['bf:province:djelgodji', 'bf:province:karo-peli'],
        '63' => ['bf:province:yagha'], '70' => ['bf:province:boulgou'],
        '71' => ['bf:province:koulpelogo'], '72' => ['bf:province:kouritenga'],
        '75' => ['bf:province:gourma'], '76' => ['bf:province:gnagna'],
        '77' => ['bf:province:komondjari'], '78' => ['bf:province:kompienga'],
        '79' => ['bf:province:dyamongou', 'bf:province:gobnangou'],
        '80' => ['bf:province:mouhoun'], '81' => ['bf:province:bale'],
        '82' => ['bf:province:banwa'], '83' => ['bf:province:koosin'],
        '84' => ['bf:province:nayala'], '85' => ['bf:province:sourou'],
        '90' => ['bf:province:houet'], '91' => ['bf:province:kenedougou'],
        '92' => ['bf:province:tuy'], '94' => ['bf:province:comoe'],
        '95' => ['bf:province:leraba'], '96' => ['bf:province:poni'],
        '97' => ['bf:province:bougouriba'], '98' => ['bf:province:ioba'],
        '99' => ['bf:province:noumbiel'],
    ];

    expect($rows)->toHaveCount(467);

    foreach ($rows as [$code, $area]) {
        expect($blocks[mb_substr((string) $code, 0, 2)])->toContain($area);
    }
});
