<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Uzbekistan\UzbekistanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('spells the Xorazm tuman Tuproqqal\'a', function (): void {
    $areas = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('uz:tuman:xorazm:tuproqqala')->name)->toBe('Tuproqqal\'a')
        ->and($areas->has('uz:tuman:xorazm:toproqqala'))->toBeFalse();
});

it('pins the verified Uzbekistan tree of 14 regions, 175 tumans and 31 cities', function (): void {
    $areas = app(UzbekistanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'region'))->toHaveCount(12)
        ->and($areas->where('type', 'republic'))->toHaveCount(1)
        ->and($areas->where('type', 'city'))->toHaveCount(32)
        ->and($areas->where('type', 'tuman'))->toHaveCount(175)
        ->and($areas->where('level', 1))->toHaveCount(14)
        ->and($areas->where('level', 2))->toHaveCount(206);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:UZ codes.
    expect($byId->get('uz:region:andijan')->code)->toBe('AN')
        ->and($byId->get('uz:region:bukhara')->code)->toBe('BU')
        ->and($byId->get('uz:region:fergana')->code)->toBe('FA')
        ->and($byId->get('uz:region:jizzakh')->code)->toBe('JI')
        ->and($byId->get('uz:region:namangan')->code)->toBe('NG')
        ->and($byId->get('uz:region:navoiy')->code)->toBe('NW')
        ->and($byId->get('uz:region:qashqadaryo')->code)->toBe('QA')
        ->and($byId->get('uz:republic:karakalpakstan')->code)->toBe('QR')
        ->and($byId->get('uz:region:samarqand')->code)->toBe('SA')
        ->and($byId->get('uz:region:sirdaryo')->code)->toBe('SI')
        ->and($byId->get('uz:region:surxondaryo')->code)->toBe('SU')
        ->and($byId->get('uz:city:tashkent-city')->code)->toBe('TK')
        ->and($byId->get('uz:region:tashkent-region')->code)->toBe('TO')
        ->and($byId->get('uz:region:xorazm')->code)->toBe('XO');

    // Per-region L2 counts (tumans + regional cities).
    $counts = $areas->where('level', 2)->countBy('parentSourceId');

    expect($counts->get('uz:region:andijan'))->toBe(16)
        ->and($counts->get('uz:region:bukhara'))->toBe(13)
        ->and($counts->get('uz:region:fergana'))->toBe(19)
        ->and($counts->get('uz:region:jizzakh'))->toBe(13)
        ->and($counts->get('uz:region:namangan'))->toBe(12)
        ->and($counts->get('uz:region:navoiy'))->toBe(11)
        ->and($counts->get('uz:region:qashqadaryo'))->toBe(16)
        ->and($counts->get('uz:republic:karakalpakstan'))->toBe(17)
        ->and($counts->get('uz:region:samarqand'))->toBe(16)
        ->and($counts->get('uz:region:sirdaryo'))->toBe(11)
        ->and($counts->get('uz:region:surxondaryo'))->toBe(15)
        ->and($counts->get('uz:region:tashkent-region'))->toBe(22)
        ->and($counts->get('uz:region:xorazm'))->toBe(13)
        ->and($counts->get('uz:city:tashkent-city'))->toBe(12);
});

it('bundles the 2140 Uzbek postcodes with the Oqoltin retarget', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('UZ', $dir . '/uzbekistan-postal-codes.csv', $dir . '/uzbekistan-postal-code-areas.csv', 'aiarmada.addressing.uzbekistan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(2140)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(2140);

    $byCode = $postcodes->keyBy->code;

    // UPU anchors: Tashkent HQ + Qo'shko'pir + city mains.
    expect((string) $byCode->get('100000')->areaSourceId)->toBe('uz:city:tashkent-city')
        ->and((string) $byCode->get('100123')->areaSourceId)->toBe('uz:city:tashkent-city')
        ->and((string) $byCode->get('220605')->areaSourceId)->toBe('uz:tuman:xorazm:qoshkopir')
        ->and((string) $byCode->get('140100')->areaSourceId)->toBe('uz:city:samarqand:samarqand')
        ->and((string) $byCode->get('190100')->areaSourceId)->toBe('uz:city:surxondaryo:termiz')
        ->and((string) $byCode->get('230100')->areaSourceId)->toBe('uz:city:karakalpakstan:nukus');

    // M6 fix: the Sardoba PAB serves Oqoltin-t (decree scope + OSM), not
    // Sardoba-t; the 1208xx Paxtaobod block stays on Sardoba-t.
    expect((string) $byCode->get('120501')->areaSourceId)->toBe('uz:tuman:sirdaryo:oqoltin')
        ->and((string) $byCode->get('120502')->areaSourceId)->toBe('uz:tuman:sirdaryo:oqoltin')
        ->and((string) $byCode->get('120503')->areaSourceId)->toBe('uz:tuman:sirdaryo:oqoltin')
        ->and((string) $byCode->get('120504')->areaSourceId)->toBe('uz:tuman:sirdaryo:oqoltin')
        ->and((string) $byCode->get('120505')->areaSourceId)->toBe('uz:tuman:sirdaryo:oqoltin')
        ->and((string) $byCode->get('120801')->areaSourceId)->toBe('uz:tuman:sirdaryo:sardoba');

    // Tricky zone mappings (district-center tables, transliteration).
    expect((string) $byCode->get('170401')->areaSourceId)->toBe('uz:tuman:andijan:boston')
        ->and((string) $byCode->get('120301')->areaSourceId)->toBe('uz:tuman:sirdaryo:guliston')
        ->and((string) $byCode->get('170614')->areaSourceId)->toBe('uz:tuman:andijan:andijon')
        ->and((string) $byCode->get('190710')->areaSourceId)->toBe('uz:tuman:surxondaryo:oltinsoy')
        ->and((string) $byCode->get('210303')->areaSourceId)->toBe('uz:tuman:navoiy:tomdi')
        ->and((string) $byCode->get('220901')->areaSourceId)->toBe('uz:city:xorazm:xiva')
        ->and((string) $byCode->get('220902')->areaSourceId)->toBe('uz:tuman:xorazm:xiva');

    // Tashkent city carries its 162 codes at L1 by design.
    expect($postcodes->where('areaSourceId', 'uz:city:tashkent-city'))->toHaveCount(162);

    // Gap pins: Shahrisabz city, Urgut, Qibray and the Tashkent tumans
    // stay codeless (follow-up fill inventory, not this pass).
    expect($postcodes->where('areaSourceId', 'uz:city:qashqadaryo:shahrisabz'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'uz:tuman:samarqand:urgut'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'uz:tuman:tashkent-region:qibray'))->toHaveCount(0)
        ->and($postcodes->where('areaSourceId', 'uz:tuman:tashkent-city:yunusobod'))->toHaveCount(0);
});
