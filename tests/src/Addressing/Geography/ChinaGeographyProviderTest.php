<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\China\ChinaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 33 L1 divisions with 333 prefectures under L1 parents', function (): void {
    $areas = app(ChinaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(366)
        ->and($l1)->toHaveCount(33)
        ->and($l2)->toHaveCount(333)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('cn:province:henan')->code)->toBe('HA')
        ->and($byId->get('cn:municipality:shanghai')->code)->toBe('SH')
        ->and($byId->get('cn:autonomous_region:guangxi')->code)->toBe('GX')
        ->and($byId->get('cn:prefecture_city:tongling')->parentSourceId)->toBe('cn:province:anhui')
        ->and($byId->get('cn:prefecture_city:haidong')->parentSourceId)->toBe('cn:province:qinghai');
});

it('pins the B19 postal surgery: 16 retargets, 2 swaps, 1 drop, 5 fills', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CN', $dir . '/china-postal-codes.csv', $dir . '/china-postal-code-areas.csv', 'aiarmada.addressing.china');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(2353)
        ->and($postcodes)->toHaveCount(2353);

    // GeoNames-admin2 retargets.
    expect($byCode->get('015400')->first()->areaSourceId)->toBe('cn:prefecture_city:bayannur')
        ->and($byCode->get('121000')->first()->areaSourceId)->toBe('cn:prefecture_city:jinzhou')
        ->and($byCode->get('244100')->first()->areaSourceId)->toBe('cn:prefecture_city:tongling')
        ->and($byCode->get('246700')->first()->areaSourceId)->toBe('cn:prefecture_city:tongling')
        ->and($byCode->get('276000')->first()->areaSourceId)->toBe('cn:prefecture_city:linyi')
        ->and($byCode->get('317300')->first()->areaSourceId)->toBe('cn:prefecture_city:taizhou-zhejiang')
        ->and($byCode->get('810600')->first()->areaSourceId)->toBe('cn:prefecture_city:haidong');

    // Swaps + drop + surviving twin.
    expect($byCode->has('057800'))->toBeFalse()
        ->and($byCode->get('054900')->first()->areaSourceId)->toBe('cn:prefecture_city:xingtai')
        ->and($byCode->has('040000'))->toBeFalse()
        ->and($byCode->get('041000')->first()->areaSourceId)->toBe('cn:prefecture_city:linfen')
        ->and($byCode->has('671100'))->toBeFalse()
        ->and($byCode->get('651100')->first()->areaSourceId)->toBe('cn:prefecture_city:yuxi');

    // Zero-link prefectures now covered + holds kept.
    expect($byCode->get('158100')->first()->areaSourceId)->toBe('cn:prefecture_city:jixi')
        ->and($byCode->get('666100')->first()->areaSourceId)->toBe('cn:autonomous_prefecture:xishuangbanna')
        ->and($byCode->get('838000')->first()->areaSourceId)->toBe('cn:prefecture_city:turpan')
        ->and($byCode->get('665000')->first()->areaSourceId)->toBe('cn:prefecture_city:pu-er')
        ->and($byCode->get('452600')->first()->areaSourceId)->toBe('cn:prefecture_city:zhoukou');
});
