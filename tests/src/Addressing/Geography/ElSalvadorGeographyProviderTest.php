<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\ElSalvador\ElSalvadorGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships the post-reform 44 municipalities under 14 departments', function (): void {
    $areas = app(ElSalvadorGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(44)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sv:municipality:central-san-salvador')->name)->toBe('Central San Salvador')
        ->and($byId->get('sv:municipality:acajutla-acajutla-western-sonsonate')->parentSourceId)->toBe('sv:department:sonsonate');
});

it('pins the verified SV tree of 14 departments and 44 municipalities', function (): void {
    $areas = app(ElSalvadorGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'department'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'sv:department:ahuachapan'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'sv:department:cabanas'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sv:department:chalatenango'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'sv:department:cuscatlan'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sv:department:la-libertad'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'sv:department:la-paz'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'sv:department:la-union'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sv:department:morazan'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sv:department:san-miguel'))->toHaveCount(3)
        ->and($areas->where('parentSourceId', 'sv:department:san-salvador'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'sv:department:san-vicente'))->toHaveCount(2)
        ->and($areas->where('parentSourceId', 'sv:department:santa-ana'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'sv:department:sonsonate'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'sv:department:usulutan'))->toHaveCount(3);

    // B9 inline fix carried forward: La Libertad x6 + La Paz x3 were
    // mis-parented to Cuscatlan, San Miguel x3 to Morazan (names carry
    // their department). ISO 3166-2:SV codes on departments.
    expect($byId->get('sv:department:san-salvador')->code)->toBe('SS')
        ->and($byId->get('sv:municipality:coastal-la-libertad')->parentSourceId)->toBe('sv:department:la-libertad')
        ->and($byId->get('sv:municipality:central-la-paz')->parentSourceId)->toBe('sv:department:la-paz')
        ->and($byId->get('sv:municipality:western-san-miguel')->parentSourceId)->toBe('sv:department:san-miguel');
});

it('bundles the verified 262-code overlay with exact municipality primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SV', $dir . '/el-salvador-postal-codes.csv', $dir . '/el-salvador-postal-code-areas.csv', 'aiarmada.addressing.el_salvador');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(262)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(262)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(262)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // San Salvador anchor: capital 1101 + 1115-1132 old-municipality codes.
    // Composition per the official reform annex (es-wiki Anexo, D.O.
    // 14/06/2023): Centro keeps Cuscatancingo/Delgado, Sur takes San
    // Marcos/Santo Tomas/Santiago Texacuangos; en-wiki's 6/6/2 split is
    // stale and was NOT followed.
    expect($primary('1101'))->toBe('sv:municipality:central-san-salvador')
        ->and($primary('1115'))->toBe('sv:municipality:southern-san-salvador')
        ->and($primary('1116'))->toBe('sv:municipality:eastern-san-salvador')
        ->and($primary('1117'))->toBe('sv:municipality:eastern-san-salvador')
        ->and($primary('1118'))->toBe('sv:municipality:central-san-salvador')
        ->and($primary('1119'))->toBe('sv:municipality:central-san-salvador')
        ->and($primary('1120'))->toBe('sv:municipality:central-san-salvador')
        ->and($primary('1121'))->toBe('sv:municipality:central-san-salvador')
        ->and($primary('1122'))->toBe('sv:municipality:northern-san-salvador')
        ->and($primary('1123'))->toBe('sv:municipality:western-san-salvador')
        ->and($primary('1124'))->toBe('sv:municipality:northern-san-salvador')
        ->and($primary('1125'))->toBe('sv:municipality:northern-san-salvador')
        ->and($primary('1126'))->toBe('sv:municipality:western-san-salvador')
        ->and($primary('1127'))->toBe('sv:municipality:southern-san-salvador')
        ->and($primary('1128'))->toBe('sv:municipality:southern-san-salvador')
        ->and($primary('1129'))->toBe('sv:municipality:eastern-san-salvador')
        ->and($primary('1130'))->toBe('sv:municipality:southern-san-salvador')
        ->and($primary('1131'))->toBe('sv:municipality:southern-san-salvador')
        ->and($primary('1132'))->toBe('sv:municipality:eastern-san-salvador');

    // Department-capital anchors (old-municipality code -> new municipality).
    expect($primary('1201'))->toBe('sv:municipality:eastern-cabanas')
        ->and($primary('1301'))->toBe('sv:municipality:southern-chalatenango')
        ->and($primary('1401'))->toBe('sv:municipality:southern-cuscatlan')
        ->and($primary('1501'))->toBe('sv:municipality:southern-la-libertad')
        ->and($primary('1601'))->toBe('sv:municipality:eastern-la-paz')
        ->and($primary('1701'))->toBe('sv:municipality:southern-san-vicente')
        ->and($primary('2101'))->toBe('sv:municipality:central-ahuachapan')
        ->and($primary('2201'))->toBe('sv:municipality:santa-ana-el-salvador-central-santa-ana')
        ->and($primary('2301'))->toBe('sv:municipality:central-sonsonate')
        ->and($primary('2302'))->toBe('sv:municipality:acajutla-acajutla-western-sonsonate')
        ->and($primary('3101'))->toBe('sv:municipality:southern-la-union')
        ->and($primary('3201'))->toBe('sv:municipality:southern-morazan')
        ->and($primary('3301'))->toBe('sv:municipality:central-san-miguel')
        ->and($primary('3401'))->toBe('sv:municipality:eastern-usulutan');

    // Every new municipality holds at least one code (singletons: Santa Ana
    // 2201, Acajutla 2302); no codeless municipalities, no dual links.
    expect($postcodes->pluck('areaSourceId')->unique())->toHaveCount(44);
});
