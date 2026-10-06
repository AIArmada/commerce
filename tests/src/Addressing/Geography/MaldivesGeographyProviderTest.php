<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Maldives tree of 18 atolls, 5 cities and 192 islands', function (): void {
    $areas = app(MaldivesGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'atoll'))->toHaveCount(18)
        ->and($areas->where('type', 'city'))->toHaveCount(5)
        ->and($areas->where('type', 'island'))->toHaveCount(192)
        ->and($areas->where('level', 1))->toHaveCount(23)
        ->and($areas->where('level', 2))->toHaveCount(192);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:MV codes (Gnaviyani 29 absorbed by Fuvahmulah city;
    // FVM/KUH/THD are provider city codes beyond ISO).
    expect($byId->get('mv:city:addu')->code)->toBe('01')
        ->and($byId->get('mv:atoll:alif-dhaal')->code)->toBe('00')
        ->and($byId->get('mv:atoll:alif-alif')->code)->toBe('02')
        ->and($byId->get('mv:atoll:lhaviyani')->code)->toBe('03')
        ->and($byId->get('mv:atoll:vaavu')->code)->toBe('04')
        ->and($byId->get('mv:atoll:laamu')->code)->toBe('05')
        ->and($byId->get('mv:atoll:haa-alif')->code)->toBe('07')
        ->and($byId->get('mv:atoll:thaa')->code)->toBe('08')
        ->and($byId->get('mv:atoll:meemu')->code)->toBe('12')
        ->and($byId->get('mv:atoll:raa')->code)->toBe('13')
        ->and($byId->get('mv:atoll:faafu')->code)->toBe('14')
        ->and($byId->get('mv:atoll:dhaalu')->code)->toBe('17')
        ->and($byId->get('mv:atoll:baa')->code)->toBe('20')
        ->and($byId->get('mv:atoll:haa-dhaalu')->code)->toBe('23')
        ->and($byId->get('mv:atoll:shaviyani')->code)->toBe('24')
        ->and($byId->get('mv:atoll:noonu')->code)->toBe('25')
        ->and($byId->get('mv:atoll:kaafu')->code)->toBe('26')
        ->and($byId->get('mv:atoll:gaafu-alif')->code)->toBe('27')
        ->and($byId->get('mv:atoll:gaafu-dhaalu')->code)->toBe('28')
        ->and($byId->get('mv:city:male')->code)->toBe('MLE')
        ->and($byId->get('mv:city:fuvahmulah')->code)->toBe('FVM')
        ->and($byId->get('mv:city:kulhudhuffushi')->code)->toBe('KUH')
        ->and($byId->get('mv:city:thinadhoo')->code)->toBe('THD')
        ->and($byId->has('mv:atoll:male'))->toBeFalse()
        ->and($byId->has('mv:atoll:gnaviyani'))->toBeFalse();

    // Per-atoll island counts (Addu + Fuvahmulah parent their own islands;
    // Kulhudhuffushi/Thinadhoo/Malé islands stay under their atolls).
    $counts = $areas->where('level', 2)->countBy('parentSourceId');

    expect($counts->get('mv:atoll:raa'))->toBe(15)
        ->and($counts->get('mv:atoll:haa-alif'))->toBe(14)
        ->and($counts->get('mv:atoll:shaviyani'))->toBe(14)
        ->and($counts->get('mv:atoll:haa-dhaalu'))->toBe(13)
        ->and($counts->get('mv:atoll:noonu'))->toBe(13)
        ->and($counts->get('mv:atoll:baa'))->toBe(13)
        ->and($counts->get('mv:atoll:thaa'))->toBe(13)
        ->and($counts->get('mv:atoll:kaafu'))->toBe(12)
        ->and($counts->get('mv:atoll:alif-dhaal'))->toBe(10)
        ->and($counts->get('mv:atoll:laamu'))->toBe(10)
        ->and($counts->get('mv:atoll:alif-alif'))->toBe(9)
        ->and($counts->get('mv:atoll:gaafu-alif'))->toBe(9)
        ->and($counts->get('mv:atoll:gaafu-dhaalu'))->toBe(9)
        ->and($counts->get('mv:atoll:meemu'))->toBe(8)
        ->and($counts->get('mv:atoll:dhaalu'))->toBe(7)
        ->and($counts->get('mv:atoll:lhaviyani'))->toBe(6)
        ->and($counts->get('mv:city:addu'))->toBe(6)
        ->and($counts->get('mv:atoll:vaavu'))->toBe(5)
        ->and($counts->get('mv:atoll:faafu'))->toBe(5)
        ->and($counts->get('mv:city:fuvahmulah'))->toBe(1);
});

it('bundles the 199 island postcodes with 3 shared-code multis', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MV', $dir . '/maldives-postal-codes.csv', $dir . '/maldives-postal-code-areas.csv', 'aiarmada.addressing.maldives');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(202)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(199);

    $byCode = $postcodes->groupBy(static fn ($row): string => (string) $row->code);
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // Live-scheme anchors (Postcodebase-verified; UPU 2004 table is stale).
    expect($primary('00040'))->toBe('mv:island:mahibadhoo')
        ->and($primary('10010'))->toBe('mv:island:fulidhoo')
        ->and($primary('10030'))->toBe('mv:island:felidhoo')
        ->and($primary('11050'))->toBe('mv:island:muli')
        ->and($primary('13080'))->toBe('mv:island:kudahuvadhoo')
        ->and($primary('14110'))->toBe('mv:island:veymandoo')
        ->and($primary('15080'))->toBe('mv:island:fonadhoo')
        ->and($primary('16040'))->toBe('mv:island:gaafu-alif:nilandhoo')
        ->and($primary('17100'))->toBe('mv:island:gaafu-dhaalu:thinadhoo')
        ->and($primary('19020'))->toBe('mv:island:addu:hithadhoo')
        ->and($primary('02110'))->toBe('mv:island:kulhudhuffushi')
        ->and($primary('23000'))->toBe('mv:island:hulhumale')
        ->and($primary('06060'))->toBe('mv:island:dharavandhoo')
        ->and($primary('09040'))->toBe('mv:island:mathiveri');

    // Multi-code islands carry every ward/village code.
    expect($byCode->get('18011')->firstWhere('isPrimary', true)->areaSourceId)->toBe('mv:island:fuvahmulah')
        ->and($byCode->get('18018')->firstWhere('isPrimary', true)->areaSourceId)->toBe('mv:island:fuvahmulah')
        ->and($postcodes->where('areaSourceId', 'mv:island:fuvahmulah'))->toHaveCount(8)
        ->and($postcodes->where('areaSourceId', 'mv:island:gan'))->toHaveCount(3)
        ->and($postcodes->where('areaSourceId', 'mv:island:isdhoo'))->toHaveCount(2)
        ->and($postcodes->where('areaSourceId', 'mv:island:maavah'))->toHaveCount(2)
        ->and($primary('17080'))->toBe('mv:island:fares-maathodaa')
        ->and($primary('17090'))->toBe('mv:island:fares-maathodaa');

    // The three multis: 05020 dual-island, 02110 + 17100 city secondaries.
    $legs = static fn (string $code): array => $byCode->get($code)
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->sortKeys()
        ->all();

    expect($legs('05020'))->toBe(['mv:island:inguraidhoo' => true, 'mv:island:vaadhoo' => false])
        ->and($legs('02110'))->toBe(['mv:city:kulhudhuffushi' => false, 'mv:island:kulhudhuffushi' => true])
        ->and($legs('17100'))->toBe(['mv:city:thinadhoo' => false, 'mv:island:gaafu-dhaalu:thinadhoo' => true]);

    // Four islands stay codeless: Malé + Villimalé street ranges and the
    // Ookolhufinolhu resort by design; Dhuvaafaru absent in every source.
    expect($postcodes->where('areaSourceId', 'mv:island:male'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mv:island:villimale'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mv:island:ookolhufinolhu'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'mv:island:dhuvaafaru'))->toHaveCount(0);
});
