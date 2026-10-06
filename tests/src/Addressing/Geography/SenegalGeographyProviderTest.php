<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('exposes corrected Senegalese region slugs and names', function (): void {
    $areas = app(SenegalGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('sn:region:diourbel')->name)->toBe('Diourbel')
        ->and($areas->get('sn:region:diourbel')->code)->toBe('DB')
        ->and($areas->get('sn:region:tambacounda')->name)->toBe('Tambacounda')
        ->and($areas->get('sn:region:tambacounda')->code)->toBe('TC')
        ->and($areas->get('sn:region:thies')->name)->toBe('Thiès')
        ->and($areas->get('sn:region:thies')->type)->toBe('region')
        ->and($areas->has('sn:region:diourbel-region'))->toBeFalse()
        ->and($areas->has('sn:region:tambacounda-region'))->toBeFalse()
        ->and($areas->has('sn:region:thies-region'))->toBeFalse();
});

it('ships 46 departments under 14 regions with the Keur Massar split', function (): void {
    $areas = app(SenegalGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    // Region -> department counts per the Departments of Senegal oracle.
    $expected = [
        'sn:region:dakar' => 5, 'sn:region:diourbel' => 3, 'sn:region:fatick' => 3,
        'sn:region:kaffrine' => 4, 'sn:region:kaolack' => 3, 'sn:region:kedougou' => 3,
        'sn:region:kolda' => 3, 'sn:region:louga' => 3, 'sn:region:matam' => 3,
        'sn:region:saint-louis' => 3, 'sn:region:sedhiou' => 3, 'sn:region:tambacounda' => 4,
        'sn:region:thies' => 3, 'sn:region:ziguinchor' => 3,
    ];

    expect($l2)->toHaveCount(46)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    foreach ($expected as $region => $count) {
        expect($l2->where('parentSourceId', $region))->toHaveCount($count, $region);
    }

    // Keur Massar, 46th department since 28 May 2021 (split from Pikine).
    expect($byId->get('sn:department:keur-massar')->parentSourceId)->toBe('sn:region:dakar')
        ->and($byId->get('sn:department:keur-massar')->name)->toBe('Keur Massar');
});

it('links the official bureau anchors to their departments', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SN', $dir . '/senegal-postal-codes.csv', $dir . '/senegal-postal-code-areas.csv', 'aiarmada.addressing.senegal');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(182)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(163)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(163)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // La Poste official bureau rows (postal-data.js) + UPU SEN profile examples.
    expect($primary('10200'))->toBe('sn:department:dakar') // Dakar RP
        ->and($primary('16500'))->toBe('sn:department:pikine') // Thiaroye
        ->and($primary('10000'))->toBe('sn:department:dakar')
        ->and($primary('27000'))->toBe('sn:department:ziguinchor')
        ->and($primary('22300'))->toBe('sn:department:mbacke') // Touba
        ->and($primary('24000'))->toBe('sn:department:kaolack') // Kaolack RP
        ->and($primary('27400'))->toBe('sn:department:kolda')
        ->and($primary('27022'))->toBe('sn:department:sedhiou');
});

it('pins the corrected cross-department links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $rows = array_map(str_getcsv(...), file($dir . '/senegal-postal-code-areas.csv'));
    array_shift($rows);

    $links = [];
    foreach ($rows as [$code, $area]) {
        $links[$code][] = $area;
    }

    // Corrected secondaries (M2): 20600 gains Tivaouane (Mbayene CR);
    // 24027 retargets to Fatick (Thiare Ndialgui CR).
    expect($links['20600'])->toEqualCanonicalizing(['sn:department:thies', 'sn:department:tivaouane'])
        ->and($links['24027'])->toBe(['sn:department:fatick'])
        ->and($links['27022'])->toEqualCanonicalizing(['sn:department:sedhiou', 'sn:department:goudomp'])
        ->and($links['27400'])->toEqualCanonicalizing(['sn:department:kolda', 'sn:department:medina-yoro-foulah'])
        ->and($links['17000'])->toEqualCanonicalizing(['sn:department:pikine', 'sn:department:keur-massar'])
        ->and($links['32800'])->toEqualCanonicalizing(['sn:department:dagana', 'sn:department:podor']);

    // Dropped secondaries stay dropped (single-link after M2).
    foreach (['23200' => 'sn:department:m-bour', '24018' => 'sn:department:foundiougne',
              '24030' => 'sn:department:kaolack', '25400' => 'sn:department:koumpentoum',
              '26018' => 'sn:department:goudiry', '27009' => 'sn:department:oussouye',
              '27406' => 'sn:department:velingara', '30600' => 'sn:department:kebemer',
              '31000' => 'sn:department:louga', '22100' => 'sn:department:mbacke'] as $code => $sole) {
        expect($links[$code])->toBe([$sole], $code);
    }

    $multi = array_filter($links, static fn ($v): bool => count($v) > 1);

    expect($multi)->toHaveCount(19)
        ->and($rows)->toHaveCount(182);
});
