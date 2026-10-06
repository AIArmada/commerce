<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Armenia\ArmeniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 10 regions plus Yerevan with 70 municipalities and 12 districts', function (): void {
    $areas = app(ArmeniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(11)
        ->and($l2->where('type', 'municipality'))->toHaveCount(70)
        ->and($l2->where('type', 'district'))->toHaveCount(12)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('am:city:yerevan')->type)->toBe('city');
});

it('pins the verified AM tree of 8/5/8/5/11/11/6/7/4/5/12', function (): void {
    $areas = app(ArmeniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('parentSourceId', 'am:region:aragatsotn'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'am:region:ararat'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'am:region:armavir'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'am:region:gegharkunik'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'am:region:kotayk'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'am:region:lori'))->toHaveCount(11)
        ->and($areas->where('parentSourceId', 'am:region:shirak'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'am:region:syunik'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'am:region:tavush'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'am:region:vayots-dzor'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'am:city:yerevan'))->toHaveCount(12);

    // Khoy is the 8th Armavir community (mtad.am settlement list).
    expect($byId->get('am:municipality:khoy')->parentSourceId)->toBe('am:region:armavir');
});

it('bundles the B11-verified 781-code Haypost overlay with zero duals', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AM', $dir . '/armenia-postal-codes.csv', $dir . '/armenia-postal-code-areas.csv', 'aiarmada.addressing.armenia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(781)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(781)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(781)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue();

    $byCode = $postcodes->groupBy->code;
    $primary = static fn (string $code): string => (string) $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId;

    // B11 fills + retarget + keep.
    expect($primary('0109'))->toBe('am:district:kentron')
        ->and($primary('0236'))->toBe('am:municipality:ashtarak')
        ->and($primary('3518'))->toBe('am:municipality:sisian')
        ->and($primary('2102'))->toBe('am:municipality:tashir');

    // Manual-fix anchors (Haypost typos resolved via address village).
    expect($primary('0725'))->toBe('am:municipality:artashat')
        ->and($primary('0614'))->toBe('am:municipality:vedi')
        ->and($primary('3019'))->toBe('am:municipality:artik')
        ->and($primary('0513'))->toBe('am:municipality:talin')
        ->and($primary('1741'))->toBe('am:municipality:alaverdi')
        ->and($primary('2032'))->toBe('am:municipality:pambak')
        ->and($primary('2610'))->toBe('am:municipality:akhuryan')
        ->and($primary('0919'))->toBe('am:municipality:metsamor')
        ->and($primary('1128'))->toBe('am:municipality:khoy')
        ->and($primary('1817'))->toBe('am:municipality:spitak')
        ->and($primary('3401'))->toBe('am:municipality:meghri')
        ->and($primary('2410'))->toBe('am:municipality:nor-hachn')
        ->and($primary('4215'))->toBe('am:municipality:berd');

    // Yerevan district anchors.
    expect($primary('0010'))->toBe('am:district:kentron')
        ->and($primary('0022'))->toBe('am:district:avan')
        ->and($primary('0074'))->toBe('am:district:shengavit');

    // Zero duals: every code links exactly once, all primary.
    $multi = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->all();
    expect($multi)->toBe([]);
});
