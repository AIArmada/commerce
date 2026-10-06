<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Morocco\MoroccoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Morocco tree of 12 regions, 13 prefectures, and 62 provinces', function (): void {
    $areas = app(MoroccoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(87)
        ->and($areas->where('type', 'region'))->toHaveCount(12)
        ->and($areas->where('type', 'prefecture'))->toHaveCount(13)
        ->and($areas->where('type', 'province'))->toHaveCount(62)
        ->and($areas->where('parentSourceId', 'ma:region:tanger-tetouan-al-hoceima'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ma:region:l-oriental'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ma:region:fes-meknes'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ma:region:rabat-sale-kenitra'))->toHaveCount(7)
        ->and($areas->where('parentSourceId', 'ma:region:beni-mellal-khenifra'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ma:region:casablanca-settat'))->toHaveCount(9)
        ->and($areas->where('parentSourceId', 'ma:region:marrakech-safi'))->toHaveCount(8)
        ->and($areas->where('parentSourceId', 'ma:region:draa-tafilalet'))->toHaveCount(5)
        ->and($areas->where('parentSourceId', 'ma:region:souss-massa'))->toHaveCount(6)
        ->and($areas->where('parentSourceId', 'ma:region:guelmim-oued-noun'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ma:region:laayoune-sakia-el-hamra'))->toHaveCount(4)
        ->and($areas->where('parentSourceId', 'ma:region:dakhla-oued-ed-dahab'))->toHaveCount(2);

    // ISO 3166-2:MA region codes.
    expect($byId->get('ma:region:tanger-tetouan-al-hoceima')->code)->toBe('01')
        ->and($byId->get('ma:region:l-oriental')->code)->toBe('02')
        ->and($byId->get('ma:region:fes-meknes')->code)->toBe('03')
        ->and($byId->get('ma:region:rabat-sale-kenitra')->code)->toBe('04')
        ->and($byId->get('ma:region:beni-mellal-khenifra')->code)->toBe('05')
        ->and($byId->get('ma:region:casablanca-settat')->code)->toBe('06')
        ->and($byId->get('ma:region:marrakech-safi')->code)->toBe('07')
        ->and($byId->get('ma:region:draa-tafilalet')->code)->toBe('08')
        ->and($byId->get('ma:region:souss-massa')->code)->toBe('09')
        ->and($byId->get('ma:region:guelmim-oued-noun')->code)->toBe('10')
        ->and($byId->get('ma:region:laayoune-sakia-el-hamra')->code)->toBe('11')
        ->and($byId->get('ma:region:dakhla-oued-ed-dahab')->code)->toBe('12');

    // Deliberate deviations from the ISO table spellings (Prefectures-list +
    // Poste Maroc usage): Taroudant, Mohammedia, Aït diacritics.
    expect($byId->get('ma:province:taroudant')->code)->toBe('TAR')
        ->and($byId->get('ma:prefecture:mohammedia')->code)->toBe('MOH')
        ->and($byId->get('ma:province:chtouka-ait-baha')->parentSourceId)->toBe('ma:region:souss-massa')
        ->and($byId->get('ma:province:nouaceur')->parentSourceId)->toBe('ma:region:casablanca-settat')
        ->and($byId->get('ma:province:aousserd')->parentSourceId)->toBe('ma:region:dakhla-oued-ed-dahab');
});

it('pins the verified Morocco postcode map: 2083 codes over 2088 links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MA', $dir . '/morocco-postal-codes.csv', $dir . '/morocco-postal-code-areas.csv', 'aiarmada.addressing.morocco');

    $links = $source->postalCodes()->collect();

    expect($links)->toHaveCount(2088)
        ->and($links->pluck('code')->unique())->toHaveCount(2083)
        ->and($links->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($links->where('isPrimary', true)->pluck('code')->unique())->toHaveCount(2083);

    $byCode = $links->groupBy->code;

    // Poste Maroc annuaire filings: Oulad Ayyad 35224 sits under Taounate
    // (both annuaire volumes), Inezgane quartiers 80100/80650 under Inezgane.
    expect($byCode->get('35224')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:taounate'])
        ->and($byCode->get('80100')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:inezgane-ait-melloul'])
        ->and($byCode->get('80650')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:inezgane-ait-melloul'])
        ->and($byCode->get('29004')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:mediouna'])
        ->and($byCode->get('86603')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:inezgane-ait-melloul']);

    // Exactly the five adjudicated duals carry secondaries.
    $multis = $byCode->filter(static fn ($rows): bool => $rows->count() > 1)->keys()->map(static fn ($code): string => (string) $code)->sort()->values()->all();
    expect($multis)->toBe(['29004', '35224', '80100', '80650', '86603']);

    // xx119 Casablanca phantoms dropped (no official trace anywhere).
    expect($byCode->has('10119'))->toBeFalse()
        ->and($byCode->has('20119'))->toBeFalse()
        ->and($byCode->has('28119'))->toBeFalse()
        ->and($byCode->has('30119'))->toBeFalse()
        ->and($byCode->has('70119'))->toBeFalse()
        ->and($byCode->has('80119'))->toBeFalse();

    // UPU MAR anchors: 10000 Rabat, 52000 Errachidia.
    expect($byCode->get('10000')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:rabat'])
        ->and($byCode->get('52000')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:errachidia']);

    // Adjudicated keeps: GN-wins singles, Berrechid-under-Settat,
    // Guercif Nougd, Skhirate under a mislabeled annuaire header.
    expect($byCode->get('90052')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:tanger-assilah'])
        ->and($byCode->get('16175')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:sidi-kacem'])
        ->and($byCode->get('26105')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:settat'])
        ->and($byCode->get('35113')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:province:guercif'])
        ->and($byCode->get('12050')->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ma:prefecture:skhirate-temara']);

    // Per-area primary counts (moved areas + anchors).
    $counts = $links->where('isPrimary', true)->countBy(static fn ($row): string => (string) $row->areaSourceId);

    expect($counts->get('ma:prefecture:casablanca'))->toBe(135)
        ->and($counts->get('ma:prefecture:agadir-ida-ou-tanane'))->toBe(38)
        ->and($counts->get('ma:prefecture:inezgane-ait-melloul'))->toBe(17)
        ->and($counts->get('ma:province:taza'))->toBe(70)
        ->and($counts->get('ma:province:taounate'))->toBe(59)
        ->and($counts->get('ma:prefecture:rabat'))->toBe(52)
        ->and($counts->get('ma:province:taroudant'))->toBe(82)
        ->and($counts->get('ma:province:tiznit'))->toBe(67)
        ->and($counts->sum())->toBe(2083);

    // The 13 new/small provinces stay codeless (Poste Maroc files their
    // localities under the parent provinces in its own directory).
    foreach (['ma:prefecture:m-diq-fnideq', 'ma:province:berrechid', 'ma:province:driouch', 'ma:province:fquih-ben-salah', 'ma:province:midelt', 'ma:province:ouezzane', 'ma:province:rehamna', 'ma:province:sidi-bennour', 'ma:province:sidi-ifni', 'ma:province:sidi-slimane', 'ma:province:tarfaya', 'ma:province:tinghir', 'ma:province:youssoufia'] as $sid) {
        expect($counts->get($sid, 0))->toBe(0);
    }
});
