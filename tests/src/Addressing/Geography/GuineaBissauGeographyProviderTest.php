<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 38 sectors under regions with parent links', function (): void {
    $areas = app(GuineaBissauGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(38)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('parentSourceId', 'gw:region:bafata'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'gw:region:gabu'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'gw:region:biombo'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'gw:region:cacheu'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'gw:region:oio'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'gw:region:bolama'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'gw:region:quinara'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'gw:region:tombali'))->toHaveCount(5)
        ->and($byId->get('gw:sector:xitole')->parentSourceId)->toBe('gw:region:bafata')
        ->and($byId->get('gw:sector:uno')->parentSourceId)->toBe('gw:region:bolama')
        ->and($byId->get('gw:sector:komo')->parentSourceId)->toBe('gw:region:tombali');
});

it('uses the verified region-article spellings', function (): void {
    $byId = app(GuineaBissauGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // The en.wiki Sectors list pipes the Bafata sector display unaccented,
    // but the Bafatá-region article infobox, the town article, pt.wiki,
    // Mapanet, 56ok, and OSM all read Bafatá. Fixed in M2 verification.
    expect($byId->get('gw:sector:bafata')->name)->toBe('Bafatá')
        ->and($byId->get('gw:sector:gabu')->name)->toBe('Gabú')
        ->and($byId->get('gw:sector:bissora')->name)->toBe('Bissorã')
        ->and($byId->get('gw:sector:mansoa')->name)->toBe('Mansôa')
        ->and($byId->get('gw:sector:catio')->name)->toBe('Catió')
        ->and($byId->get('gw:sector:sao-domingos')->name)->toBe('São Domingos')
        ->and($byId->get('gw:sector:canghungo')->name)->toBe('Canghungo');
});

it('ships the 9 ISO 3166-2:GW first-level divisions without statistical rows', function (): void {
    $areas = app(GuineaBissauGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $l1 = $areas->where('level', 1)->keyBy->sourceId;

    expect($l1)->toHaveCount(9)
        ->and($l1->get('gw:region:bafata')->code)->toBe('BA')
        ->and($l1->get('gw:region:biombo')->code)->toBe('BM')
        ->and($l1->get('gw:autonomous_sector:bissau')->code)->toBe('BS')
        ->and($l1->get('gw:region:bolama')->code)->toBe('BL')
        ->and($l1->get('gw:region:cacheu')->code)->toBe('CA')
        ->and($l1->get('gw:region:gabu')->code)->toBe('GA')
        ->and($l1->get('gw:region:oio')->code)->toBe('OI')
        ->and($l1->get('gw:region:quinara')->code)->toBe('QU')
        ->and($l1->get('gw:region:tombali')->code)->toBe('TO')
        ->and($areas->pluck('code')->filter()->intersect(['L', 'N', 'S'])->isEmpty())->toBeTrue()
        ->and($areas->pluck('name')->map(fn ($n) => mb_strtolower((string) $n))
            ->intersect(['leste', 'norte', 'sul'])->isEmpty())->toBeTrue();
});

it('links the 52 Mapanet codes to sectors with exactly one primary each', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GW', $dir . '/guinea-bissau-postal-codes.csv', $dir . '/guinea-bissau-postal-code-areas.csv', 'aiarmada.addressing.guinea_bissau');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(62)
        ->and($postcodes->pluck('code')->unique())->toHaveCount(52)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\d{4}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->relationshipType === 'served_by'))->toBeTrue()
        ->and($postcodes->where('isPrimary', true))->toHaveCount(52)
        ->and($postcodes->where('isPrimary', true)->pluck('code')->unique())->toHaveCount(52);

    $primary = $postcodes->where('isPrimary', true)->keyBy->code;

    // Bissau autonomous sector holds its 12-code set (1160-1890).
    $bissau = ['1160', '1190', '1220', '1250', '1280', '1400', '1430', '1490', '1600', '1640', '1670', '1890'];
    foreach ($bissau as $code) {
        expect((string) $primary->get($code)->areaSourceId)->toBe('gw:autonomous_sector:bissau');
    }

    // Bolama triple: Uno primary (6/10 OSM rows incl. Uno town).
    expect((string) $primary->get('9300')->areaSourceId)->toBe('gw:sector:uno')
        ->and($postcodes->where('code', '9300')->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['gw:sector:bubaque', 'gw:sector:caravela']);

    // 5000 triple: Bafatá primary (Bijine -> Galomaro, Geba -> Gã-Mamudo).
    expect((string) $primary->get('5000')->areaSourceId)->toBe('gw:sector:bafata')
        ->and($postcodes->where('code', '5000')->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all())
        ->toBe(['gw:sector:galomaro', 'gw:sector:gamamundo']);

    // Duals: seat holds primary throughout (OSM per-row votes).
    expect((string) $primary->get('1950')->areaSourceId)->toBe('gw:sector:nhacra')
        ->and((string) $primary->get('3200')->areaSourceId)->toBe('gw:sector:mansaba')
        ->and((string) $primary->get('3300')->areaSourceId)->toBe('gw:sector:bissora')
        // 3600 judgment: OSM votes Nhacra 3-1, but the Mansoa row is
        // Mansoa town itself, so the sector capital holds primary.
        ->and((string) $primary->get('3600')->areaSourceId)->toBe('gw:sector:mansoa')
        ->and((string) $primary->get('6400')->areaSourceId)->toBe('gw:sector:boe')
        ->and((string) $primary->get('8300')->areaSourceId)->toBe('gw:sector:cacine')
        ->and((string) $primary->get('8400')->areaSourceId)->toBe('gw:sector:quebo');

    $secondaries = $postcodes->where('isPrimary', false)->groupBy->code->map(
        fn ($rows) => $rows->pluck('areaSourceId')->sort()->values()->all()
    );
    expect($secondaries->sortKeys()->all())->toBe([
        '3200' => ['gw:sector:mansoa'],
        '3300' => ['gw:sector:mansaba'],
        '3600' => ['gw:sector:nhacra'],
        '5000' => ['gw:sector:galomaro', 'gw:sector:gamamundo'],
        '6400' => ['gw:sector:gabu'],
        '8300' => ['gw:sector:bedanda'],
        '8400' => ['gw:sector:bedanda'],
        '9300' => ['gw:sector:bubaque', 'gw:sector:caravela'],
    ]);

    // 5300 stays Galomaro-only: the lone Xitole vote is a mislabeled
    // 'Bafatá' centroid row, outvoted 3-1 by town rows.
    expect($postcodes->where('code', '5300'))->toHaveCount(1)
        ->and((string) $primary->get('5300')->areaSourceId)->toBe('gw:sector:galomaro');

    // Codeless in source: zero of 150 Mapanet rows fall in these sectors.
    foreach (['gw:sector:bigene', 'gw:sector:catio', 'gw:sector:komo', 'gw:sector:bolama'] as $sid) {
        expect($postcodes->where('areaSourceId', $sid))->toBeEmpty();
    }

    // Cedex/PO-box layer stays out (UPU 1000/1011, OSM 1021, Codex 1029).
    foreach (['1000', '1011', '1021', '1029'] as $code) {
        expect($postcodes->where('code', $code))->toBeEmpty();
    }
});
