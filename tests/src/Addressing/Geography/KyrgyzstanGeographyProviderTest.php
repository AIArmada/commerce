<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Kyrgyzstan\KyrgyzstanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 44 districts under regions and cities with parent links', function (): void {
    $areas = app(KyrgyzstanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(44)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'kg:city:bishkek'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'kg:region:chuy'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'kg:region:issyk-kul'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'kg:region:naryn'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'kg:region:talas'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'kg:region:batken'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'kg:region:jalal-abad'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'kg:region:osh'))->toHaveCount(7)
        // Kara-Buura renamed Aitmatov by Law KR 2023-04-10 No. 82
        // (Jogorku Kenesh 2023-02-22); slug follows the new name.
        ->and($byId->has('kg:district:kara-buura'))->toBeFalse()
        ->and($byId->get('kg:district:aitmatov')->name)->toBe('Aitmatov')
        ->and($byId->get('kg:district:aitmatov')->parentSourceId)->toBe('kg:region:talas')
        ->and($byId->get('kg:district:toguz-toro')->parentSourceId)->toBe('kg:region:jalal-abad')
        ->and($byId->get('kg:district:toktogul')->parentSourceId)->toBe('kg:region:jalal-abad');
});

it('uses the oracle spellings with diacritics', function (): void {
    $byId = app(KyrgyzstanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($byId->get('kg:district:alamudun')->name)->toBe('Alamüdün')
        ->and($byId->get('kg:district:chuy')->name)->toBe('Chüy')
        ->and($byId->get('kg:district:jeti-oguz')->name)->toBe('Jeti-Ögüz')
        ->and($byId->get('kg:district:tup')->name)->toBe('Tüp')
        ->and($byId->get('kg:district:ozgon')->name)->toBe('Özgön');
});

it('maps the 9 ISO 3166-2:KG first-level divisions', function (): void {
    $mappings = app(KyrgyzstanGeographyProvider::class)->stateAreaMappings();

    expect(array_keys($mappings))->toEqualCanonicalizing(['B', 'GB', 'C', 'Y', 'J', 'N', 'GO', 'O', 'T']);
});

it('links the 919-code overlay with the M2 corrections', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KG', $dir . '/kyrgyzstan-postal-codes.csv', $dir . '/kyrgyzstan-postal-code-areas.csv', 'aiarmada.addressing.kyrgyzstan');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(919)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^72[0-5]\d{3}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Jalal-Abad city (renamed Manas 2025; region unchanged) sits at
    // region level: 720900-720909 city + 720910 Kachkynchy (city-admin
    // village per RU-wiki + Kyrgyz Post hub filing). Moved off Suzak.
    foreach (range(900, 910) as $suffix) {
        expect((string) $byCode->get('720' . $suffix)->areaSourceId)->toBe('kg:region:jalal-abad');
    }

    // Kara-Köl holds 721000-721003 at region level (Mapanet Karakul rows
    // + RU-wiki + Kara-Köl city archive), not Jalal-Abad city.
    // Tash-Kumyr holds 721400-721408; Balykchy 721900-721906.
    expect((string) $byCode->get('721000')->areaSourceId)->toBe('kg:region:jalal-abad')
        ->and((string) $byCode->get('721400')->areaSourceId)->toBe('kg:region:jalal-abad')
        ->and((string) $byCode->get('721900')->areaSourceId)->toBe('kg:region:issyk-kul');

    // Toguz-Toro fill: 721500-721506 (Mapanet leaf + Kazaman 721500 via
    // state archive + OSM + Kyrgyz Post branch structure).
    foreach (range(500, 506) as $suffix) {
        expect((string) $byCode->get('721' . $suffix)->areaSourceId)->toBe('kg:district:toguz-toro');
    }

    // Toktogul fill: full 721600-721621 run (Mapanet leaf; town codes
    // 721600/721615/721616 RU-wiki-corroborated).
    foreach (range(600, 621) as $suffix) {
        expect((string) $byCode->get('721' . $suffix)->areaSourceId)->toBe('kg:district:toktogul');
    }

    // Seat anchors: Tokmok 724200 (RU-wiki 722000 is stale Soviet;
    // 722000 is live at Kyzylsuu, Jeti-Ögüz), Naryn 722900
    // (722600 is live at At-Bashy), Gulcha 723000, Daroot-Korgon
    // 723700, Kyzyl-Adyr 723900 under Aitmatov.
    expect((string) $byCode->get('724200')->areaSourceId)->toBe('kg:district:chuy')
        ->and((string) $byCode->get('724915')->areaSourceId)->toBe('kg:district:chuy')
        ->and((string) $byCode->get('722000')->areaSourceId)->toBe('kg:district:jeti-oguz')
        ->and((string) $byCode->get('722900')->areaSourceId)->toBe('kg:district:naryn')
        ->and((string) $byCode->get('722600')->areaSourceId)->toBe('kg:district:at-bashy')
        ->and((string) $byCode->get('723000')->areaSourceId)->toBe('kg:district:alay')
        ->and((string) $byCode->get('723300')->areaSourceId)->toBe('kg:district:kara-suu')
        ->and((string) $byCode->get('723700')->areaSourceId)->toBe('kg:district:chong-alay')
        ->and((string) $byCode->get('723900')->areaSourceId)->toBe('kg:district:aitmatov')
        ->and((string) $byCode->get('725000')->areaSourceId)->toBe('kg:district:ysyk-ata');
});
