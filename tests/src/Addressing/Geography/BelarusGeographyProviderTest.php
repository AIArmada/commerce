<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Belarus\BelarusGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 6 oblasts plus Minsk city with ISO codes', function (): void {
    $areas = app(BelarusGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(7)
        ->and($areas->get('by:oblast:brest')->code)->toBe('BR')
        ->and($areas->get('by:oblast:gomel')->code)->toBe('HO')
        ->and($areas->get('by:city:minsk')->code)->toBe('HM')
        ->and($areas->get('by:oblast:grodno')->code)->toBe('HR')
        ->and($areas->get('by:oblast:mogilev')->code)->toBe('MA')
        ->and($areas->get('by:oblast:minsk')->code)->toBe('MI')
        ->and($areas->get('by:oblast:vitebsk')->code)->toBe('VI');
});

it('ships 118 raions with per-oblast parents', function (): void {
    $areas = app(BelarusGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(118)
        ->and($l2->where('parentSourceId', 'by:oblast:brest'))->toHaveCount(16)
        ->and($l2->where('parentSourceId', 'by:oblast:vitebsk'))->toHaveCount(21)
        ->and($l2->where('parentSourceId', 'by:oblast:gomel'))->toHaveCount(21)
        ->and($l2->where('parentSourceId', 'by:oblast:grodno'))->toHaveCount(17)
        ->and($l2->where('parentSourceId', 'by:oblast:minsk'))->toHaveCount(22)
        ->and($l2->where('parentSourceId', 'by:oblast:mogilev'))->toHaveCount(21)
        ->and($byId->get('by:district:byaroza')->parentSourceId)->toBe('by:oblast:brest')
        ->and($byId->get('by:district:vyerkhnyadzvinsk')->name)->toBe('Vyerkhnyadzvinsk');
});

it('links Byaroza-cluster codes to Byaroza not Brest', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BY', $dir . '/belarus-postal-codes.csv', $dir . '/belarus-postal-code-areas.csv', 'aiarmada.addressing.belarus');

    $byCode = $source->postalCodes()->collect()->keyBy->code;

    // GeoNames has no Byaroza-raion admin2; the cluster inherited admin2=Brest.
    expect($byCode->get('225210')->areaSourceId)->toBe('by:district:byaroza')
        ->and($byCode->get('225209')->areaSourceId)->toBe('by:district:byaroza')
        ->and($byCode->get('225247')->areaSourceId)->toBe('by:district:byaroza')
        ->and($byCode->get('224000')->areaSourceId)->toBe('by:district:brest');
});

it('pins retargets, fills, ties, and drops', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BY', $dir . '/belarus-postal-codes.csv', $dir . '/belarus-postal-code-areas.csv', 'aiarmada.addressing.belarus');

    $byCode = $source->postalCodes()->collect()->keyBy->code;

    expect($byCode->get('211440')->areaSourceId)->toBe('by:district:polotsk')
        ->and($byCode->get('211620')->areaSourceId)->toBe('by:district:vyerkhnyadzvinsk')
        ->and($byCode->get('231470')->areaSourceId)->toBe('by:district:dzyatlava')
        ->and($byCode->get('247711')->areaSourceId)->toBe('by:district:kalinkavichy')
        ->and($byCode->get('222024')->areaSourceId)->toBe('by:district:krupki')
        ->and($byCode->get('247407')->areaSourceId)->toBe('by:district:svyetlahorsk')
        ->and($byCode->get('211631')->areaSourceId)->toBe('by:district:vyerkhnyadzvinsk')
        ->and($byCode->get('213910')->areaSourceId)->toBe('by:district:klichaw')
        ->and($byCode->get('211443')->areaSourceId)->toBe('by:district:polotsk')
        ->and($byCode->get('231894')->areaSourceId)->toBe('by:district:vawkavysk')
        ->and($byCode->get('211227')->areaSourceId)->toBe('by:district:lyozna')
        ->and($byCode->get('220024')->areaSourceId)->toBe('by:city:minsk')
        ->and($byCode->get('222834')->areaSourceId)->toBe('by:district:pukhavichy')
        ->and($byCode->has('213918'))->toBeFalse()
        ->and($byCode->has('247047'))->toBeFalse();
});
