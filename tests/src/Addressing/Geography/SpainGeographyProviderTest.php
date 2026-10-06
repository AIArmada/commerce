<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Spain\SpainGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 50 provinces under communities plus Ceuta and Melilla with parent links', function (): void {
    $areas = app(SpainGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);

    expect($l1->where('type', 'autonomous_community'))->toHaveCount(17)
        ->and($l1->where('type', 'autonomous_city'))->toHaveCount(2)
        ->and($areas->where('type', 'province'))->toHaveCount(50)
        ->and($byId->get('es:province:almeria')->parentSourceId)->toBe('es:autonomous_community:andalusia');
});

it('bundles the B9-verified 11068-code 5-digit overlay with 26 dual-province links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('ES', $dir . '/spain-postal-codes.csv', $dir . '/spain-postal-code-areas.csv', 'aiarmada.addressing.spain');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(11094)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(11068)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(11068)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;
    $secondary = static fn (string $code): ?string => (string) ($byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId ?? '');

    // Madrid + Barcelona + autonomous-city cluster anchors.
    expect($primary('28001'))->toBe('es:province:madrid')
        ->and($primary('28004'))->toBe('es:province:madrid')
        ->and($primary('28050'))->toBe('es:province:madrid')
        ->and($primary('28071'))->toBe('es:province:madrid')
        ->and($primary('08001'))->toBe('es:province:barcelona')
        ->and($primary('08002'))->toBe('es:province:barcelona')
        ->and($primary('08070'))->toBe('es:province:barcelona')
        ->and($primary('08970'))->toBe('es:province:barcelona')
        ->and($primary('51001'))->toBe('es:autonomous_city:ceuta')
        ->and($primary('52001'))->toBe('es:autonomous_city:melilla');

    // B9 fills: renumber gaps + operator-asserted codes missing from GeoNames.
    expect($primary('01070'))->toBe('es:province:araba')
        ->and($primary('09117'))->toBe('es:province:burgos')
        ->and($primary('21431'))->toBe('es:province:huelva')
        ->and($primary('24359'))->toBe('es:province:leon')
        ->and($primary('50221'))->toBe('es:province:zaragoza');

    // B9 primary moves: Correos + CartoCiudad/INE overrule stale GeoNames.
    expect($primary('28310'))->toBe('es:province:madrid')
        ->and($primary('42269'))->toBe('es:province:soria')
        ->and($primary('06691'))->toBe('es:province:caceres');

    // Kept cross-prefix primaries + single-source holds (unchanged singles).
    expect($primary('14449'))->toBe('es:province:ciudad-real')
        ->and($primary('22806'))->toBe('es:province:zaragoza')
        ->and($primary('26127'))->toBe('es:province:soria')
        ->and($primary('18538'))->toBe('es:province:granada')
        ->and($primary('23296'))->toBe('es:province:jaen')
        ->and($primary('45217'))->toBe('es:province:toledo');

    // B9 new dual legs (Correos + independent second signal each).
    expect($secondary('13249'))->toBe('es:province:albacete')
        ->and($secondary('14113'))->toBe('es:province:sevilla')
        ->and($secondary('16612'))->toBe('es:province:albacete')
        ->and($secondary('18312'))->toBe('es:province:cordoba')
        ->and($secondary('26528'))->toBe('es:province:zaragoza')
        ->and($secondary('28600'))->toBe('es:province:toledo')
        ->and($secondary('45216'))->toBe('es:province:madrid');

    // B9 dropped legs leave single primaries behind.
    expect($secondary('28189'))->toBe('')
        ->and($secondary('28310'))->toBe('')
        ->and($secondary('42269'))->toBe('')
        ->and($primary('28189'))->toBe('es:province:madrid');

    // Exact dual inventory: 19 kept (Treviño trio, enclaves, border splits) + 7 new.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->map(static fn ($code): string => mb_str_pad((string) $code, 5, '0', STR_PAD_LEFT))->sort()->values()->all();
    expect($multi)->toBe(['01118', '01211', '01427', '03657', '08281', '13110', '13249', '14113', '16612', '18312',
        '22583', '22584', '22808', '26212', '26528', '28190', '28600', '33554', '34492', '39232', '39250',
        '39419', '43421', '44591', '45216', '50686']);

    // B9 removals: 87 dead codes absent (operator 404/fuzzy + zero municipal holders).
    $dead = ['03115', '03459', '03519', '03589', '06012', '07608', '08805', '09233', '09285', '09360', '10150',
        '10396', '11200', '11574', '12512', '13434', '15591', '16112', '16147', '19131', '26226', '27795', '27844',
        '28279', '28339', '28419', '28459', '28513', '28851', '28870', '29395', '30070', '30071', '30080', '30196',
        '30535', '32774', '33566', '33588', '33599', '33627', '33673', '33692', '33721', '33724', '33732', '33736',
        '33737', '33748', '33799', '33837', '33838', '33912', '34006', '34130', '34260', '36281', '36282', '36339',
        '37139', '37197', '37198', '37207', '37269', '37458', '37467', '37479', '37491', '37608', '37715', '38411',
        '40299', '42147', '42154', '42175', '42350', '43518', '43729', '44470', '45489', '46249', '47018', '49290',
        '50176', '50411', '50592', '50596'];
    expect($dead)->toHaveCount(87);
    $still = array_values(array_filter($dead, static fn (string $code): bool => $byCode->has($code)));
    expect($still)->toBe([]);

    // Per-province primary counts pin the add/move provinces.
    $counts = $postcodes->where('isPrimary', true)->countBy('areaSourceId');
    expect($counts->get('es:province:araba'))->toBe(81)
        ->and($counts->get('es:province:burgos'))->toBe(216)
        ->and($counts->get('es:province:huelva'))->toBe(106)
        ->and($counts->get('es:province:leon'))->toBe(419)
        ->and($counts->get('es:province:zaragoza'))->toBe(277)
        ->and($counts->get('es:province:madrid'))->toBe(315)
        ->and($counts->get('es:province:caceres'))->toBe(247)
        ->and($counts->get('es:province:soria'))->toBe(100);
});
