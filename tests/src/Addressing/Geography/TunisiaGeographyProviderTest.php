<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Tunisia tree of 24 governorates and 279 delegations', function (): void {
    $areas = app(TunisiaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'governorate'))->toHaveCount(24)
        ->and($areas->where('type', 'delegation'))->toHaveCount(279)
        ->and($areas->where('level', 1))->toHaveCount(24)
        ->and($areas->where('parentSourceId', 'tn:governorate:tunis'))->toHaveCount(21)
        ->and($areas->where('parentSourceId', 'tn:governorate:nabeul'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'tn:governorate:sousse'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'tn:governorate:sfax'))->toHaveCount(16)
        ->and($areas->where('parentSourceId', 'tn:governorate:sidi-bouzid'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'tn:governorate:bizerte'))->toHaveCount(14)
        ->and($areas->where('parentSourceId', 'tn:governorate:ariana'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'tn:governorate:zaghouan'))->toHaveCount(6);

    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:TN codes (articles dropped, Kebili/Medenine unaccented).
    expect($byId->get('tn:governorate:tunis')->code)->toBe('11')
        ->and($byId->get('tn:governorate:ariana')->code)->toBe('12')
        ->and($byId->get('tn:governorate:kef')->code)->toBe('33')
        ->and($byId->get('tn:governorate:kebili')->code)->toBe('73')
        ->and($byId->get('tn:governorate:medenine')->code)->toBe('82')
        ->and($byId->get('tn:delegation:sfax-ville')->parentSourceId)->toBe('tn:governorate:sfax')
        ->and($byId->get('tn:delegation:sousse-medina')->parentSourceId)->toBe('tn:governorate:sousse');
});

it('bundles the 969 delegation postcodes with adjudicated primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TN', $dir . '/tunisia-postal-codes.csv', $dir . '/tunisia-postal-code-areas.csv', 'aiarmada.addressing.tunisia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(982)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(969);

    $primaries = $postcodes->where('isPrimary', true)->keyBy->code;

    expect($primaries)->toHaveCount(969);

    // UPU tunEn anchors: 1002 Belvedere, 8170 Bou Salem, 3100 Kairouan.
    expect((string) $primaries->get('1002')->areaSourceId)->toBe('tn:delegation:cite-el-khadra')
        ->and((string) $primaries->get('8170')->areaSourceId)->toBe('tn:delegation:bou-salem')
        ->and((string) $primaries->get('3100')->areaSourceId)->toBe('tn:delegation:kairouan-sud');

    // Seat fills: Sfax 3000, Sousse 4000, Nabeul 8000.
    expect((string) $primaries->get('3000')->areaSourceId)->toBe('tn:delegation:sfax-ville')
        ->and((string) $primaries->get('4000')->areaSourceId)->toBe('tn:delegation:sousse-medina')
        ->and((string) $primaries->get('8000')->areaSourceId)->toBe('tn:delegation:nabeul');

    // Namesake fills: Rades 2040, Ras Jebel 7070, Sidi Hassine 1095.
    expect((string) $primaries->get('2040')->areaSourceId)->toBe('tn:delegation:rades')
        ->and((string) $primaries->get('7070')->areaSourceId)->toBe('tn:delegation:ras-jebel')
        ->and((string) $primaries->get('1095')->areaSourceId)->toBe('tn:delegation:sidi-hassine');

    // OSM post-office nodes: Oued Rmal 3023, Oued Chaabouni 3071 (Sfax Ouest).
    expect((string) $primaries->get('3023')->areaSourceId)->toBe('tn:delegation:sfax-ouest')
        ->and((string) $primaries->get('3071')->areaSourceId)->toBe('tn:delegation:sfax-ouest');

    // Shared-code primaries kept: Khadra-triple 1002, Soukra 2035, Sud seats.
    expect((string) $primaries->get('2035')->areaSourceId)->toBe('tn:delegation:la-soukra')
        ->and((string) $primaries->get('3200')->areaSourceId)->toBe('tn:delegation:tataouine-sud')
        ->and((string) $primaries->get('4100')->areaSourceId)->toBe('tn:delegation:medenine-sud');

    // Per-governorate primary counts (2035 primaried Ariana, not Tunis).
    $areas = app(TunisiaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;
    $counts = $primaries->countBy(static fn ($row) => (string) $areas->get((string) $row->areaSourceId)->parentSourceId);

    expect($counts->get('tn:governorate:tunis'))->toBe(54)
        ->and($counts->get('tn:governorate:sfax'))->toBe(79)
        ->and($counts->get('tn:governorate:medenine'))->toBe(63)
        ->and($counts->get('tn:governorate:nabeul'))->toBe(62)
        ->and($counts->get('tn:governorate:monastir'))->toBe(53)
        ->and($counts->get('tn:governorate:bizerte'))->toBe(48);
});
