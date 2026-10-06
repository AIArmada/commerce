<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Tajikistan\TajikistanGeographyProvider;

it('pins the verified Tajikistan tree of 5 regions and 69 districts/cities', function (): void {
    $areas = app(TajikistanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    // ISO 3166-2:TJ first level: DU/GB/KT/RA/SU.
    expect($areas->where('level', 1))->toHaveCount(5)
        ->and($l2)->toHaveCount(69)
        ->and($l2->where('type', 'district'))->toHaveCount(51)
        ->and($l2->where('type', 'city'))->toHaveCount(18)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    // Districts-of-Tajikistan oracle: Sughd 10+8, Khatlon 21+4,
    // GBAO 7+1, RRP 9+5 (incl. Dushanbe city), Dushanbe capital 4.
    expect($l2->where('parentSourceId', 'tj:region:sughd'))->toHaveCount(18)
        ->and($l2->where('parentSourceId', 'tj:region:khatlon'))->toHaveCount(25)
        ->and($l2->where('parentSourceId', 'tj:autonomous_region:gorno-badakhshan'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'tj:districts_under_republic_administration:nohiyahoi-tobei-jumhuri'))->toHaveCount(14)
        ->and($l2->where('parentSourceId', 'tj:capital_territory:dushanbe'))->toHaveCount(4);

    // Dushanbe is an extraregional capital city seated under RRP in the
    // oracle; Khorugh is GBAO's only city; Bokhtar sits under Khatlon.
    expect($byId->get('tj:city:dushanbe')->parentSourceId)->toBe('tj:districts_under_republic_administration:nohiyahoi-tobei-jumhuri')
        ->and($byId->get('tj:city:khorugh')->parentSourceId)->toBe('tj:autonomous_region:gorno-badakhshan')
        ->and($byId->get('tj:city:bokhtar')->parentSourceId)->toBe('tj:region:khatlon')
        ->and($byId->get('tj:district:ibn-sina')->parentSourceId)->toBe('tj:capital_territory:dushanbe');
});

it('pins the verified region codes and post-Soviet rename spellings', function (): void {
    $byId = app(TajikistanGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // ISO 3166-2:TJ codes; RA keeps the pre-2017 capitalisation.
    expect($byId->get('tj:capital_territory:dushanbe')->code)->toBe('DU')
        ->and($byId->get('tj:autonomous_region:gorno-badakhshan')->code)->toBe('GB')
        ->and($byId->get('tj:region:khatlon')->code)->toBe('KT')
        ->and($byId->get('tj:districts_under_republic_administration:nohiyahoi-tobei-jumhuri')->code)->toBe('RA')
        ->and($byId->get('tj:region:sughd')->name)->toBe('Sughd');

    // Post-Soviet renames already applied (Leninabad, Nau, Uroteppa,
    // Taboshar, Kayrakkum, Chkalovsk, Qurghonteppa, Sarband,
    // Kofarnihon, Jirgatol, Gharm, Leninskiy are gone).
    expect($byId->get('tj:district:spitamen')->name)->toBe('Spitamen')
        ->and($byId->get('tj:city:istaravshan')->name)->toBe('Istaravshan')
        ->and($byId->get('tj:city:istiqlol')->name)->toBe('Istiqlol')
        ->and($byId->get('tj:city:guliston')->name)->toBe('Guliston')
        ->and($byId->get('tj:city:buston')->name)->toBe('Buston')
        ->and($byId->get('tj:city:bokhtar')->name)->toBe('Bokhtar')
        ->and($byId->get('tj:city:levakant')->name)->toBe('Levakant')
        ->and($byId->get('tj:district:jayhun')->name)->toBe('Jayhun')
        ->and($byId->get('tj:district:jaloliddin-balkhi')->name)->toBe('Jaloliddin Balkhi')
        ->and($byId->get('tj:district:lakhsh')->name)->toBe('Lakhsh')
        ->and($byId->get('tj:district:rasht')->name)->toBe('Rasht')
        ->and($byId->get('tj:district:rudaki')->name)->toBe('Rudaki')
        ->and($byId->get('tj:city:vahdat')->name)->toBe('Vahdat');

    // Deliberate deviation: oracle display says "Roshtqala" but the
    // target article is "Roshtqal'a District" (native Роштқалъа).
    expect($byId->get('tj:district:roshtqal-a')->name)->toBe("Roshtqal'a");

    // Dushanbe city districts per the oracle.
    expect($byId->get('tj:district:firdavsi')->name)->toBe('Firdavsi')
        ->and($byId->get('tj:district:ismail-somoni')->name)->toBe('Ismail Somoni')
        ->and($byId->get('tj:district:shohmansur')->name)->toBe('Shohmansur');
});

it('ships no postal overlay yet (verify-only-tree, gap program open)', function (): void {
    // The 6-digit system is live but no overlay ships: the Tajik Post
    // index table is truncated mid-Khatlon (22/69 L2 codeless) with 14
    // shared codes, and no second directory exists. A future seed must
    // resolve the gap program first -- and update this pin deliberately.
    $dir = __DIR__.'/../../../../packages/addressing/resources/geography';

    expect(file_exists($dir.'/tajikistan-postal-codes.csv'))->toBeFalse()
        ->and(file_exists($dir.'/tajikistan-postal-code-areas.csv'))->toBeFalse();
});
