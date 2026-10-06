<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 15 counties with ISO codes and the spaced River Cess name', function (): void {
    $areas = app(LiberiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'county'))->toHaveCount(15)
        // ISO 3166-2:LR county codes.
        ->and($byId->get('lr:county:bomi')->code)->toBe('BM')
        ->and($byId->get('lr:county:nimba')->code)->toBe('NI')
        ->and($byId->get('lr:county:river-gee')->code)->toBe('RG')
        // LISGIS 2022 report prints "River Cess County" (spaced).
        ->and($byId->get('lr:county:river-cess')->name)->toBe('River Cess')
        ->and($byId->get('lr:county:river-cess')->code)->toBe('RI');
});

it('ships 157 districts on the 2022 census scheme', function (): void {
    $areas = app(LiberiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(157)
        ->and($l2->where('parentSourceId', 'lr:county:bomi'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'lr:county:gbarpolu'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'lr:county:grand-bassa'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'lr:county:grand-gedeh'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'lr:county:grand-kru'))->toHaveCount(19)
        ->and($l2->where('parentSourceId', 'lr:county:lofa'))->toHaveCount(11)
        ->and($l2->where('parentSourceId', 'lr:county:margibi'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'lr:county:maryland'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'lr:county:montserrado'))->toHaveCount(15)
        ->and($l2->where('parentSourceId', 'lr:county:sinoe'))->toHaveCount(21)
        ->and($l2->where('parentSourceId', 'lr:county:river-gee'))->toHaveCount(10)
        // Census-spelling renames (LISGIS report + p-codes + 2008 report).
        ->and($byId->get('lr:district:panta')->name)->toBe('Panta')
        ->and($byId->get('lr:district:sanoyeah')->name)->toBe('Sanoyeah')
        ->and($byId->get('lr:district:quardu-boundi')->name)->toBe('Quardu Boundi')
        ->and($byId->get('lr:district:farmington')->name)->toBe('Farmington')
        ->and($byId->get('lr:district:pleebo-sodoken')->name)->toBe('Pleebo/Sodoken')
        ->and($byId->get('lr:district:neekreen')->name)->toBe('Neekreen')
        ->and($byId->get('lr:district:jeadepo')->name)->toBe('Jeadepo')
        ->and($byId->get('lr:district:sanniquellie-mahn')->name)->toBe('Sanniquellie Mahn')
        ->and($byId->get('lr:district:wee-gbehyi-mahn')->name)->toBe('Wee-Gbehyi-Mahn')
        // 2022-scheme adds (report spellings, not CDA letter-typos).
        ->and($byId->get('lr:district:gounwolaila')->parentSourceId)->toBe('lr:county:gbarpolu')
        ->and($byId->get('lr:district:owensgrove')->parentSourceId)->toBe('lr:county:grand-bassa')
        ->and($byId->get('lr:district:lukameh')->name)->toBe('Lukameh')
        ->and($byId->get('lr:district:wahasa')->name)->toBe('Wahasa')
        ->and($byId->get('lr:district:waum')->name)->toBe('Waum')
        ->and($byId->get('lr:district:barnersville-township')->name)->toBe('Barnersville Township')
        ->and($byId->get('lr:district:louisiana-township')->name)->toBe('Louisiana Township')
        ->and($byId->get('lr:district:karluway-number-1')->name)->toBe('Karluway Number 1')
        // Drops: stale statutory/old-scheme units absent from the 2022 census.
        ->and($byId->has('lr:district:gbarzon'))->toBeFalse()
        ->and($byId->has('lr:district:barrobo'))->toBeFalse()
        ->and($byId->has('lr:district:webbo'))->toBeFalse()
        ->and($byId->has('lr:district:mambah-kaba'))->toBeFalse()
        ->and($byId->has('lr:district:montserrado:commonwealth'))->toBeFalse()
        // Contested keeps: report-sealed against CDA typos.
        ->and($byId->get('lr:district:penicess')->name)->toBe('Penicess')
        ->and($byId->get('lr:district:karforh')->name)->toBe('Karforh')
        ->and($byId->get('lr:district:dugbe-river')->name)->toBe('Dugbe River');
});

it('links all 31 postcodes at county level with zero multis', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LR', $dir . '/liberia-postal-codes.csv', $dir . '/liberia-postal-code-areas.csv', 'aiarmada.addressing.liberia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(31)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(31);

    $byCode = $postcodes->keyBy->code;

    // UPU anchors: 1000 Monrovia, 4000 Buchanan.
    expect($byCode->get('1000')->areaSourceId)->toBe('lr:county:montserrado')
        ->and($byCode->get('4000')->areaSourceId)->toBe('lr:county:grand-bassa')
        ->and($byCode->get('3000')->areaSourceId)->toBe('lr:county:bong')
        ->and($byCode->get('7500')->areaSourceId)->toBe('lr:county:lofa');
});

it('seals the Appendix B print errata from the final LISGIS report', function (): void {
    $areas = app(LiberiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $l2 = $areas->where('level', 2);

    $goun = $l2->where('name', 'Gounwolaila');

    // B2 gap +17,986 seals Gounwolaila under Gbarpolu; the B3/B5 printed
    // rows (identical figures twice) are spurious dups, never added.
    expect($goun)->toHaveCount(1)
        ->and($goun->first()->parentSourceId)->toBe('lr:county:gbarpolu')
        // B11 sums exactly: no 8th Maryland district in either spelling.
        ->and($l2->whereIn('name', ['Barobo', 'Barrobo']))->toBeEmpty()
        // B4/B8 gaps seal the omitted rows as current districts.
        ->and($l2->where('name', 'Owensgrove')->first()->parentSourceId)->toBe('lr:county:grand-bassa')
        ->and($l2->where('name', 'Dugbe River')->first()->parentSourceId)->toBe('lr:county:sinoe');
});
