<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Ethiopia\EthiopiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 14 regions with 124 zones on the current scheme', function (): void {
    $areas = app(EthiopiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(14)
        ->and($l2)->toHaveCount(124)
        ->and($l2->where('parentSourceId', 'et:city:addis-ababa'))->toHaveCount(11)
        ->and($l2->where('parentSourceId', 'et:region:afar'))->toHaveCount(7)
        ->and($l2->where('parentSourceId', 'et:region:amhara'))->toHaveCount(13)
        ->and($l2->where('parentSourceId', 'et:region:benishangul-gumuz'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'et:region:central-ethiopia'))->toHaveCount(10)
        ->and($l2->where('parentSourceId', 'et:city:dire-dawa'))->toHaveCount(0)
        ->and($l2->where('parentSourceId', 'et:region:gambela'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'et:region:harari'))->toHaveCount(9)
        ->and($l2->where('parentSourceId', 'et:region:oromia'))->toHaveCount(22)
        ->and($l2->where('parentSourceId', 'et:region:sidama'))->toHaveCount(4)
        ->and($l2->where('parentSourceId', 'et:region:somali'))->toHaveCount(17)
        ->and($l2->where('parentSourceId', 'et:region:south-ethiopia'))->toHaveCount(12)
        ->and($l2->where('parentSourceId', 'et:region:southwest-ethiopia'))->toHaveCount(6)
        ->and($l2->where('parentSourceId', 'et:region:tigray'))->toHaveCount(7);
});

it('pins the B14 renames and drops against WP zone list plus region articles', function (): void {
    $areas = app(EthiopiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Renames: WP zone list + Oromia article (+ GeoNames for Hararghe/Welega).
    expect($byId->get('et:zone:borana')->name)->toBe('Borena')
        ->and($byId->get('et:zone:west-haraghe')->name)->toBe('West Hararghe')
        ->and($byId->get('et:zone:east-welega-gimbie')->name)->toBe('East Welega')
        // GeoNames ADM2 zone-form + official standard (cp town-form is Mekele).
        ->and($byId->get('et:zone:mekele')->name)->toBe('Mekelle')
        // Drops: unlinked dup + lowercase WP-list vandal rows, absent everywhere.
        ->and($byId->has('et:zone:amhara:west-gojjam'))->toBeFalse()
        ->and($byId->has('et:zone:wolkait-tegede-stit-humera-zone'))->toBeFalse()
        ->and($byId->has('et:zone:north-gojjam-zone'))->toBeFalse()
        // Survivor keeps the West Gojjam postal legs.
        ->and($byId->get('et:zone:west-gojjam')->name)->toBe('West Gojjam')
        // Contested keeps: new-zone renames and specials.
        ->and($byId->get('et:zone:gardula')->name)->toBe('Gardula')
        ->and($byId->get('et:zone:koore')->name)->toBe('Koore')
        ->and($byId->get('et:zone:mahi-rasu')->name)->toBe('Mahi Rasu')
        ->and($byId->get('et:zone:anywaa')->name)->toBe('Anywaa')
        ->and($byId->get('et:zone:east-bale')->name)->toBe('East Bale')
        ->and($byId->get('et:zone:sheger-city')->name)->toBe('Sheger City')
        ->and($byId->get('et:zone:jigjiga-special')->name)->toBe('Jigjiga Special')
        ->and($byId->get('et:zone:tog-wajale-special')->name)->toBe('Tog Wajale Special');
});

it('links 51 postcodes with Addis Ababa primary on 1000', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('ET', $dir . '/ethiopia-postal-codes.csv', $dir . '/ethiopia-postal-code-areas.csv', 'aiarmada.addressing.ethiopia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(83)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(51);

    $byCode = $postcodes->groupBy->code;

    // UPU ethEn + cheat-sheet: 1000 is Addis Ababa, North Shewa secondary.
    $legs1000 = $byCode->get('1000')->keyBy->areaSourceId;
    expect($legs1000->get('et:city:addis-ababa')->isPrimary)->toBeTrue()
        ->and($legs1000->get('et:zone:oromia:north-shewa')->isPrimary)->toBeFalse()
        // Sealed single-leg anchors.
        ->and($byCode->get('3000')->sole()->areaSourceId)->toBe('et:city:dire-dawa')
        ->and($byCode->get('3020')->sole()->areaSourceId)->toBe('et:city:dire-dawa')
        ->and($byCode->get('3040')->sole()->areaSourceId)->toBe('et:zone:shabelle')
        ->and($byCode->get('3200')->sole()->areaSourceId)->toBe('et:region:harari');

    // 2026-10-06 retry: 1230 Akaki Beseka (cheat + vendor pool + addressed
    // Beseka School PO BOX 28/1230) single to Addis; 1150 gains a Sheger
    // City secondary (EHRCO press + Anbessa bank agent data place Alem Gena
    // in Sheger/Gelan Guda), Addis primary kept.
    $legs1150 = $byCode->get('1150')->keyBy->areaSourceId;
    expect($byCode->get('1230')->sole()->areaSourceId)->toBe('et:city:addis-ababa')
        ->and($legs1150->get('et:city:addis-ababa')->isPrimary)->toBeTrue()
        ->and($legs1150->get('et:zone:sheger-city')->isPrimary)->toBeFalse();
});
