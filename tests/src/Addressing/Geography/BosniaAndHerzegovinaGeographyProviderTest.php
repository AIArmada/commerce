<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 143 municipalities under 2 entities plus terminal Brčko', function (): void {
    $areas = app(BosniaAndHerzegovinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($areas->where('level', 1))->toHaveCount(3)
        ->and($l2)->toHaveCount(143)
        // ISO 3166-2:BA entity codes.
        ->and($byId->get('ba:entity:federation-of-bosnia-and-herzegovina')->code)->toBe('BIH')
        ->and($byId->get('ba:entity:republika-srpska')->code)->toBe('SRP')
        ->and($byId->get('ba:district:brcko')->code)->toBe('BRC')
        ->and($l2->where('parentSourceId', 'ba:entity:federation-of-bosnia-and-herzegovina'))->toHaveCount(79)
        ->and($l2->where('parentSourceId', 'ba:entity:republika-srpska'))->toHaveCount(64)
        ->and($l2->where('parentSourceId', 'ba:district:brcko'))->toHaveCount(0)
        // Sarajevo city spans 4 municipalities; East Sarajevo city row kept per WP RS table.
        ->and($byId->get('ba:municipality:novi-grad-sarajevo')->name)->toBe('Novi Grad, Sarajevo')
        ->and($byId->get('ba:municipality:stari-grad-sarajevo')->name)->toBe('Stari Grad, Sarajevo')
        ->and($byId->get('ba:municipality:istocno-sarajevo')->name)->toBe('Istočno Sarajevo')
        ->and($byId->get('ba:municipality:stanari')->name)->toBe('Stanari')
        ->and($byId->get('ba:municipality:trnovo-rs')->name)->toBe('Trnovo (RS)');
});

it('links 563 postcodes with the B14 Sarajevo, Stanari and Bijeljina fixes', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BA', $dir . '/bosnia-and-herzegovina-postal-codes.csv', $dir . '/bosnia-and-herzegovina-postal-code-areas.csv', 'aiarmada.addressing.bosnia_and_herzegovina');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(625)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(563);

    $byCode = $postcodes->groupBy->code;

    // 71000 serves all 4 Sarajevo-city municipalities (UPU + BH Pošta delivery + city structure).
    $legs71000 = $byCode->get('71000')->keyBy->areaSourceId;
    expect($legs71000->get('ba:municipality:centar-sarajevo')->isPrimary)->toBeTrue()
        ->and($legs71000->get('ba:municipality:novo-sarajevo')->isPrimary)->toBeFalse()
        ->and($legs71000->get('ba:municipality:novi-grad-sarajevo')->isPrimary)->toBeFalse()
        ->and($legs71000->get('ba:municipality:stari-grad-sarajevo')->isPrimary)->toBeFalse();

    // 71123 gains Lukavica (Pošte Srpske office sits in Istočno Novo Sarajevo).
    $legs71123 = $byCode->get('71123')->keyBy->areaSourceId;
    expect($legs71123->get('ba:municipality:istocna-ilidza')->isPrimary)->toBeTrue()
        ->and($legs71123->get('ba:municipality:istocno-novo-sarajevo')->isPrimary)->toBeFalse();

    // 74208 re-primaried Stanari over Doboj (Pošte Srpske + municipality seat).
    $legs74208 = $byCode->get('74208')->keyBy->areaSourceId;
    expect($legs74208->get('ba:municipality:stanari')->isPrimary)->toBeTrue()
        ->and($legs74208->get('ba:municipality:doboj')->isPrimary)->toBeFalse();

    // 77253 re-primaried Bosanski Petrovac over Bihać (PostNet + Krnjeuša village).
    $legs77253 = $byCode->get('77253')->keyBy->areaSourceId;
    expect($legs77253->get('ba:municipality:bosanski-petrovac')->isPrimary)->toBeTrue()
        ->and($legs77253->get('ba:municipality:bihac')->isPrimary)->toBeFalse();

    // Bijeljina main swapped 76000 (retired) -> 76300 (current, 7 signals).
    expect($byCode->has('76000'))->toBeFalse()
        ->and($byCode->get('76300')->sole()->areaSourceId)->toBe('ba:municipality:bijeljina');
});

it('fills the OPEN-12 proven-live codes with dual-operator legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('BA', $dir . '/bosnia-and-herzegovina-postal-codes.csv', $dir . '/bosnia-and-herzegovina-postal-code-areas.csv', 'aiarmada.addressing.bosnia_and_herzegovina');

    $byCode = $source->postalCodes()->collect()->groupBy->code;

    // Duals: BHP/FBiH side primary, Pošte Srpske/RS side secondary.
    foreach (['71124' => 'ba:municipality:novo-sarajevo', '71213' => 'ba:municipality:ilidza', '71214' => 'ba:municipality:ilidza', '71216' => 'ba:municipality:ilidza'] as $code => $primary) {
        $legs = $byCode->get($code)->keyBy->areaSourceId;
        expect($legs->get($primary)->isPrimary)->toBeTrue()
            ->and($legs->get('ba:municipality:istocna-ilidza')->isPrimary)->toBeFalse();
    }

    // Sarajevo units follow the WP municipality split.
    expect($byCode->get('71101')->sole()->areaSourceId)->toBe('ba:municipality:centar-sarajevo')
        ->and($byCode->get('71125')->sole()->areaSourceId)->toBe('ba:municipality:novo-sarajevo')
        ->and($byCode->get('71140')->sole()->areaSourceId)->toBe('ba:municipality:stari-grad-sarajevo')
        ->and($byCode->get('71167')->sole()->areaSourceId)->toBe('ba:municipality:novi-grad-sarajevo');

    // Mostar units + beyond-65 proven fills.
    expect($byCode->get('88108')->sole()->areaSourceId)->toBe('ba:municipality:mostar')
        ->and($byCode->get('88122')->sole()->areaSourceId)->toBe('ba:municipality:mostar')
        ->and($byCode->get('72293')->sole()->areaSourceId)->toBe('ba:municipality:novi-travnik')
        ->and($byCode->get('74231')->sole()->areaSourceId)->toBe('ba:municipality:usora')
        ->and($byCode->get('73300')->sole()->areaSourceId)->toBe('ba:municipality:foca')
        ->and($byCode->get('79101')->sole()->areaSourceId)->toBe('ba:municipality:prijedor')
        ->and($byCode->get('74273')->sole()->areaSourceId)->toBe('ba:municipality:teslic')
        ->and($byCode->get('71323')->sole()->areaSourceId)->toBe('ba:municipality:vogosca')
        ->and($byCode->get('71126')->sole()->areaSourceId)->toBe('ba:municipality:istocno-novo-sarajevo');

    // Erroneous 79293 dropped (replaced by 72293); retired codes absent, successors live.
    expect($byCode->has('79293'))->toBeFalse()
        ->and($byCode->has('74321'))->toBeFalse()
        ->and($byCode->has('75000'))->toBeFalse()
        ->and($byCode->has('88267'))->toBeFalse()
        ->and($byCode->has('75101'))->toBeTrue()
        ->and($byCode->has('88266'))->toBeTrue();

    // Likely-live holds + likely-retired holds stay out of the bundle.
    foreach (['76234', '76271', '76276', '80203', '88327', '88365', '88366', '88368', '88375', '88395', '71145', '76100', '76231', '80241', '88005', '88101', '88222', '88241', '88242', '88261', '88264', '88301', '88302', '88321', '88341'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }
});
