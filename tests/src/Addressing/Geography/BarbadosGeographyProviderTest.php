<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Barbados\BarbadosGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Barbados tree of 11 parishes', function (): void {
    $areas = app(BarbadosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(11)
        ->and($areas->where('level', 1))->toHaveCount(11);

    // ISO 3166-2:BB codes, exact set (Christ Church first, then
    // Saints alphabetical).
    expect($byId->get('bb:parish:christ-church')->code)->toBe('01')
        ->and($byId->get('bb:parish:saint-andrew')->code)->toBe('02')
        ->and($byId->get('bb:parish:saint-george')->code)->toBe('03')
        ->and($byId->get('bb:parish:saint-james')->code)->toBe('04')
        ->and($byId->get('bb:parish:saint-john')->code)->toBe('05')
        ->and($byId->get('bb:parish:saint-joseph')->code)->toBe('06')
        ->and($byId->get('bb:parish:saint-lucy')->code)->toBe('07')
        ->and($byId->get('bb:parish:saint-michael')->code)->toBe('08')
        ->and($byId->get('bb:parish:saint-peter')->code)->toBe('09')
        ->and($byId->get('bb:parish:saint-philip')->code)->toBe('10')
        ->and($byId->get('bb:parish:saint-thomas')->code)->toBe('11');
});

it('bundles the 1176 Barbadian postcodes with 9 BB23 dual splits', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BB', $dir . '/barbados-postal-codes.csv', $dir . '/barbados-postal-code-areas.csv', 'aiarmada.addressing.barbados');

    $postcodes = $source->postalCodes()->collect();

    // 1176 codes / 1185 links: the 9 BB23 duals.
    expect($postcodes)->toHaveCount(1185)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(1176)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^BB\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->relationshipType === 'served_by'))->toBeTrue()
        ->and($postcodes->where('isPrimary', true))->toHaveCount(1176)
        ->and($postcodes->where('isPrimary', true)->pluck('code')->unique())->toHaveCount(1176);

    $primary = $postcodes->where('isPrimary', true)->keyBy->code;

    // Office base anchors (18 district post offices share 16 x00
    // codes): GPO Cheapside BB11000, Welches BB13000, Worthing
    // BB15000, Airport/Seawell BB16000, Oistins BB17000, Speightstown
    // BB26000 (also a finder row: "St. Peter Post Office").
    expect((string) $primary->get('BB11000')->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $primary->get('BB13000')->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $primary->get('BB15000')->areaSourceId)->toBe('bb:parish:christ-church')
        ->and((string) $primary->get('BB16000')->areaSourceId)->toBe('bb:parish:christ-church')
        ->and((string) $primary->get('BB17000')->areaSourceId)->toBe('bb:parish:christ-church')
        ->and((string) $primary->get('BB26000')->areaSourceId)->toBe('bb:parish:saint-peter');

    // Block-purity anchors: every block is single-parish except BB23.
    expect((string) $primary->get('BB14001')->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $primary->get('BB18034')->areaSourceId)->toBe('bb:parish:saint-philip')
        ->and((string) $primary->get('BB19001')->areaSourceId)->toBe('bb:parish:saint-george')
        ->and((string) $primary->get('BB20001')->areaSourceId)->toBe('bb:parish:saint-john')
        ->and((string) $primary->get('BB21002')->areaSourceId)->toBe('bb:parish:saint-joseph')
        ->and((string) $primary->get('BB22026')->areaSourceId)->toBe('bb:parish:saint-thomas')
        ->and((string) $primary->get('BB24001')->areaSourceId)->toBe('bb:parish:saint-james')
        ->and((string) $primary->get('BB25001')->areaSourceId)->toBe('bb:parish:saint-andrew')
        ->and((string) $primary->get('BB27193')->areaSourceId)->toBe('bb:parish:saint-lucy');

    // BB23 single-parish legs: the St Thomas pair and St Michael run.
    expect((string) $primary->get('BB23025')->areaSourceId)->toBe('bb:parish:saint-thomas')
        ->and((string) $primary->get('BB23026')->areaSourceId)->toBe('bb:parish:saint-thomas')
        ->and((string) $primary->get('BB23028')->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $primary->get('BB23034')->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $primary->get('BB23001')->areaSourceId)->toBe('bb:parish:saint-james');

    // Dual primaries follow the finder district majority (BB23027 is
    // a 1v1 tie broken by block context: BB23 is a St James series).
    expect((string) $primary->get('BB23006')->areaSourceId)->toBe('bb:parish:saint-james')
        ->and((string) $primary->get('BB23027')->areaSourceId)->toBe('bb:parish:saint-james')
        ->and((string) $primary->get('BB23037')->areaSourceId)->toBe('bb:parish:saint-michael');

    $secondaries = $postcodes->where('isPrimary', false);

    expect($secondaries)->toHaveCount(9)
        ->and((string) $secondaries->where('code', 'BB23006')->first()->areaSourceId)->toBe('bb:parish:saint-thomas')
        ->and((string) $secondaries->where('code', 'BB23017')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23021')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23024')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23027')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23031')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23035')->first()->areaSourceId)->toBe('bb:parish:saint-michael')
        ->and((string) $secondaries->where('code', 'BB23037')->first()->areaSourceId)->toBe('bb:parish:saint-james')
        ->and((string) $secondaries->where('code', 'BB23040')->first()->areaSourceId)->toBe('bb:parish:saint-michael');

    // Held out: BB190215 (6-digit Todds Land typo; no safe retarget).
    expect($primary->has('BB190215'))->toBeFalse();

    $counts = $primary->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts)->toHaveCount(11)
        ->and($counts->get('bb:parish:saint-michael'))->toBe(244)
        ->and($counts->get('bb:parish:christ-church'))->toBe(228)
        ->and($counts->get('bb:parish:saint-george'))->toBe(186)
        ->and($counts->get('bb:parish:saint-lucy'))->toBe(105)
        ->and($counts->get('bb:parish:saint-peter'))->toBe(95)
        ->and($counts->get('bb:parish:saint-philip'))->toBe(76)
        ->and($counts->get('bb:parish:saint-thomas'))->toBe(59)
        ->and($counts->get('bb:parish:saint-james'))->toBe(56)
        ->and($counts->get('bb:parish:saint-andrew'))->toBe(54)
        ->and($counts->get('bb:parish:saint-joseph'))->toBe(42)
        ->and($counts->get('bb:parish:saint-john'))->toBe(31);
});
