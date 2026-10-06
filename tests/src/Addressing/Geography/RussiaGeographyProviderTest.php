<?php

declare(strict_types=1);

// B11 pins for Russia (no prior RussiaGeographyProviderTest.php existed).
// Place at tests/src/Addressing/Geography/RussiaGeographyProviderTest.php.

use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Russia tree of 83 ISO federal subjects', function (): void {
    $areas = app(RussiaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(83)
        ->and($areas->where('type', 'oblast'))->toHaveCount(46)
        ->and($areas->where('type', 'republic'))->toHaveCount(21)
        ->and($areas->where('type', 'krai'))->toHaveCount(9)
        ->and($areas->where('type', 'okrug'))->toHaveCount(4)
        ->and($areas->where('type', 'federal_city'))->toHaveCount(2)
        ->and($areas->where('type', 'autonomous_oblast'))->toHaveCount(1)
        ->and($areas->where('level', 1))->toHaveCount(83);

    // ISO 3166-2:RU code roster (83/83; no CR/SEV/new-territory codes).
    expect($areas->pluck('code')->sort()->values()->all())->toBe([
        'AD', 'AL', 'ALT', 'AMU', 'ARK', 'AST', 'BA', 'BEL', 'BRY', 'BU',
        'CE', 'CHE', 'CHU', 'CU', 'DA', 'IN', 'IRK', 'IVA', 'KAM', 'KB',
        'KC', 'KDA', 'KEM', 'KGD', 'KGN', 'KHA', 'KHM', 'KIR', 'KK', 'KL',
        'KLU', 'KO', 'KOS', 'KR', 'KRS', 'KYA', 'LEN', 'LIP', 'MAG', 'ME',
        'MO', 'MOS', 'MOW', 'MUR', 'NEN', 'NGR', 'NIZ', 'NVS', 'OMS', 'ORE',
        'ORL', 'PER', 'PNZ', 'PRI', 'PSK', 'ROS', 'RYA', 'SA', 'SAK', 'SAM',
        'SAR', 'SE', 'SMO', 'SPE', 'STA', 'SVE', 'TA', 'TAM', 'TOM', 'TUL',
        'TVE', 'TY', 'TYU', 'UD', 'ULY', 'VGG', 'VLA', 'VLG', 'VOR', 'YAN',
        'YAR', 'YEV', 'ZAB',
    ]);

    // Single-level hierarchy: no parents anywhere.
    expect($areas->every(static fn ($area): bool => $area->parentSourceId === null))->toBeTrue();

    $byId = $areas->keyBy->sourceId;

    // Type/name spot pins (Nenets is an okrug; krai/okrug keep suffixes).
    expect($byId->get('ru:okrug:nenets-okrug')->code)->toBe('NEN')
        ->and($byId->get('ru:krai:zabaykalsky-krai')->name)->toBe('Zabaykalsky Krai')
        ->and($byId->get('ru:krai:kamchatka-krai')->code)->toBe('KAM')
        ->and($byId->get('ru:federal_city:moscow')->code)->toBe('MOW')
        ->and($byId->get('ru:federal_city:saint-petersburg')->code)->toBe('SPE')
        ->and($byId->get('ru:autonomous_oblast:jewish-autonomous-oblast')->code)->toBe('YEV')
        ->and($byId->get('ru:republic:sakha-yakutia')->name)->toBe('Sakha (Yakutia)')
        ->and($byId->get('ru:oblast:tyumen')->code)->toBe('TYU')
        ->and($byId->get('ru:okrug:khanty-mansi-okrug')->type)->toBe('okrug');
});

it('bundles the 43531 GeoNames RU codes as singleton subject primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('RU', $dir . '/russia-postal-codes.csv', $dir . '/russia-postal-code-areas.csv', 'aiarmada.addressing.russia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(43531)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{6}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue()
        ->and($postcodes->pluck('code')->unique())->toHaveCount(43531)
        ->and($postcodes->pluck('code')->min())->toBe('101000')
        ->and($postcodes->pluck('code')->max())->toBe('901993');

    $byCode = $postcodes->keyBy->code;
    $area = static fn (string $code): string => (string) $byCode->get($code)->areaSourceId;

    // Baikonur 468xxx (Kazakhstan) absent; no Crimea/new-territory ranges.
    foreach (['468320', '468321', '468322', '468323', '468324', '468325', '468700'] as $dropped) {
        expect($byCode->has($dropped))->toBeFalse();
    }
    expect($postcodes->every(static fn ($row): bool => ! in_array(mb_substr((string) $row->code, 0, 2), ['26', '27', '28', '29'], true)))->toBeTrue();

    // B11 fix: the 626 block sits in Tyumen, not Khanty-Mansi.
    expect($area('626150'))->toBe('ru:oblast:tyumen')
        ->and($area('626020'))->toBe('ru:oblast:tyumen')
        ->and($area('626240'))->toBe('ru:oblast:tyumen')
        ->and($area('626380'))->toBe('ru:oblast:tyumen');

    // Rescue-block anchors.
    expect($area('628011'))->toBe('ru:okrug:khanty-mansi-okrug')
        ->and($area('629000'))->toBe('ru:okrug:yamalo-nenets-okrug')
        ->and($area('166000'))->toBe('ru:okrug:nenets-okrug')
        ->and($area('679000'))->toBe('ru:autonomous_oblast:jewish-autonomous-oblast')
        ->and($area('689000'))->toBe('ru:okrug:chukotka-okrug');

    // Stale-GeoNames remaps and documented keeps.
    expect($area('672000'))->toBe('ru:krai:zabaykalsky-krai') // ex-Chita
        ->and($area('687000'))->toBe('ru:krai:zabaykalsky-krai') // Agin-Buryat
        ->and($area('683000'))->toBe('ru:krai:kamchatka-krai') // ex-Oblast
        ->and($area('688000'))->toBe('ru:krai:kamchatka-krai') // Koryak
        ->and($area('101000'))->toBe('ru:federal_city:moscow')
        ->and($area('144700'))->toBe('ru:federal_city:moscow') // UFPS-MO office in Moscow
        ->and($area('144000'))->toBe('ru:oblast:moscow-oblast');

    // Per-subject primaries (post-fix: Tyumen 485, KHM 238).
    $sc = $postcodes->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($sc)->toHaveCount(83)
        ->and($sc->get('ru:oblast:tyumen'))->toBe(485)
        ->and($sc->get('ru:okrug:khanty-mansi-okrug'))->toBe(238)
        ->and($sc->get('ru:okrug:yamalo-nenets-okrug'))->toBe(96)
        ->and($sc->get('ru:okrug:nenets-okrug'))->toBe(33)
        ->and($sc->get('ru:autonomous_oblast:jewish-autonomous-oblast'))->toBe(88)
        ->and($sc->get('ru:okrug:chukotka-okrug'))->toBe(53)
        ->and($sc->get('ru:krai:zabaykalsky-krai'))->toBe(436)
        ->and($sc->get('ru:krai:kamchatka-krai'))->toBe(119)
        ->and($sc->get('ru:federal_city:moscow'))->toBe(1422)
        ->and($sc->get('ru:oblast:arkhangelsk'))->toBe(606);

    // 3-digit prefix coherence (ru-WP table): only the two known overlaps.
    $shared = $postcodes->groupBy(static fn ($row): string => mb_substr((string) $row->code, 0, 3))
        ->filter(static fn ($rows): bool => $rows->pluck('areaSourceId')->unique()->count() > 1);

    expect($shared->keys()->map(static fn ($k): string => (string) $k)->sort()->values()->all())->toBe(['144', '901']);
});
