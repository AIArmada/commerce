<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 18 first-level areas with ISO codes and the VT/VI split', function (): void {
    $areas = app(LaosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(18)
        ->and($areas->where('type', 'prefecture'))->toHaveCount(1)
        ->and($areas->where('type', 'province'))->toHaveCount(17)
        // ISO 3166-2:LA codes; the two Vientianes share a name only.
        ->and($byId->get('la:prefecture:vientiane')->code)->toBe('VT')
        ->and($byId->get('la:province:vientiane')->code)->toBe('VI')
        ->and($byId->get('la:province:vientiane')->name)->toBe('Vientiane')
        ->and($byId->get('la:province:sainyabuli')->code)->toBe('XA')
        ->and($byId->get('la:province:xaisomboun')->code)->toBe('XS')
        ->and($byId->get('la:province:champasak')->code)->toBe('CH');
});

it('ships 148 districts with the B15 identity and code fixes', function (): void {
    $areas = app(LaosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(148)
        ->and($l2->where('parentSourceId', 'la:prefecture:vientiane'))->toHaveCount(9)
        ->and($l2->where('parentSourceId', 'la:province:vientiane'))->toHaveCount(11)
        ->and($l2->where('parentSourceId', 'la:province:savannakhet'))->toHaveCount(15)
        ->and($l2->where('parentSourceId', 'la:province:xaisomboun'))->toHaveCount(5)
        ->and($l2->where('parentSourceId', 'la:province:champasak'))->toHaveCount(10)
        ->and($l2->where('parentSourceId', 'la:province:luang-prabang'))->toHaveCount(12)
        // Meun keeps the retired-code gap (COD-AB LA1013 + 2015 census).
        ->and($byId->get('la:district:meun')->code)->toBe('10-13')
        ->and($l2->where('code', '10-11'))->toBeEmpty()
        ->and($l2->where('code', '10-12'))->toBeEmpty()
        // Savannakhet identity fixes (LSB + citypopulation + EPL Lao names).
        ->and($byId->get('la:district:xonbuly')->name)->toBe('Xonbuly')
        ->and($byId->get('la:district:xayphoothong')->name)->toBe('Xayphoothong')
        ->and($byId->get('la:district:phalanxay')->name)->toBe('Phalanxay')
        ->and($byId->has('la:district:xonaboury'))->toBeFalse()
        ->and($byId->has('la:district:xonboury'))->toBeFalse()
        ->and($byId->has('la:district:thaphalanxay'))->toBeFalse()
        // Xaisomboun rotation (census usid + uuid geocodes + village names).
        ->and($byId->get('la:district:thathom')->code)->toBe('18-02')
        ->and($byId->get('la:district:longchaeng')->code)->toBe('18-03')
        ->and($byId->get('la:district:longxan')->code)->toBe('18-05')
        // Qualifier strips (bundle convention is bare names).
        ->and($byId->get('la:district:sangthong')->name)->toBe('Sangthong')
        ->and($byId->get('la:district:mayparkngum')->name)->toBe('Mayparkngum')
        ->and($byId->has('la:district:sangthong-district'))->toBeFalse()
        ->and($byId->has('la:district:mayparkngum-district'))->toBeFalse()
        // Transliteration holds: common-English spellings kept.
        ->and($byId->get('la:district:et')->name)->toBe('Et')
        ->and($byId->get('la:district:mok-may')->name)->toBe('Mok May')
        ->and($byId->get('la:district:hinhurp')->name)->toBe('Hinhurp');
});

it('links 26 zone codes with the EPL-anchored Champasak and Xaisomboun zones', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('LA', $dir . '/laos-postal-codes.csv', $dir . '/laos-postal-code-areas.csv', 'aiarmada.addressing.laos');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(148);

    $byCode = $postcodes->groupBy->code;

    // Champasak zone moved 16010 -> 16000 (EPL: Pakse 16000, 16010 is the
    // Champasack-district office); Xaisomboun leaves 10000 for its own
    // EPL 18000 block with Anouvong primary.
    expect($byCode->has('16010'))->toBeFalse()
        ->and($byCode->get('16000'))->toHaveCount(10)
        ->and($byCode->get('16000')->where('isPrimary', true)->first()->areaSourceId)->toBe('la:district:pakse')
        ->and($byCode->get('18000'))->toHaveCount(5)
        ->and($byCode->get('18000')->where('isPrimary', true)->first()->areaSourceId)->toBe('la:district:anouvong')
        ->and($byCode->get('10000'))->toHaveCount(11)
        // Vientiane-prefecture sub-zones verified against Mapanet 9/9.
        ->and($byCode->get('01160')->first()->areaSourceId)->toBe('la:district:xaysetha')
        ->and($byCode->get('01120')->first()->areaSourceId)->toBe('la:district:hadxayfong')
        ->and($byCode->get('01080')->first()->areaSourceId)->toBe('la:district:sangthong');
});
