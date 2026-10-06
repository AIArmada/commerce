<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Norway\NorwayGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 17 L1 areas with 357 municipalities under L1 parents', function (): void {
    $areas = app(NorwayGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(374)
        ->and($l1)->toHaveCount(17)
        ->and($l2)->toHaveCount(357)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    // Post-2024 county scheme (Viken 30 split).
    expect($byId->get('no:county:oslo')->code)->toBe('03')
        ->and($byId->get('no:county:stfold')->code)->toBe('31')
        ->and($byId->get('no:county:akershus')->code)->toBe('32')
        ->and($byId->get('no:county:buskerud')->code)->toBe('33')
        ->and($byId->get('no:county:vestfold')->code)->toBe('39')
        ->and($byId->get('no:county:telemark')->code)->toBe('40')
        ->and($byId->get('no:county:troms')->code)->toBe('55')
        ->and($byId->get('no:county:finnmark')->code)->toBe('56')
        ->and($byId->get('no:arctic_region:svalbard')->code)->toBe('21')
        ->and($byId->get('no:municipality:haram')->code)->toBe('1580');
});

it('pins the B19 Bring pass: 5110 codes with 14 fills and 40 drops', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NO', $dir . '/norway-postal-codes.csv', $dir . '/norway-postal-code-areas.csv', 'aiarmada.addressing.norway');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(5110)
        ->and($postcodes)->toHaveCount(5110);

    // logg-nye fills.
    expect($byCode->get('1426')->first()->areaSourceId)->toBe('no:municipality:as')
        ->and($byCode->get('4075')->first()->areaSourceId)->toBe('no:municipality:stavanger')
        ->and($byCode->get('8866')->first()->areaSourceId)->toBe('no:municipality:alstahaug')
        ->and($byCode->get('9653')->first()->areaSourceId)->toBe('no:municipality:hammerfest');

    // Svalbard gap fills.
    foreach (['9173', '9174', '9175', '9176'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('no:arctic_region:svalbard');
    }

    // Retired codes dropped.
    foreach (['0031', '2809', '4672', '6506', '8128', '9290'] as $code) {
        expect($byCode->has($code))->toBeFalse();
    }

    // S-code holds: 0046/0047 out, 0018/0045 kept.
    expect($byCode->has('0046'))->toBeFalse()
        ->and($byCode->has('0047'))->toBeFalse()
        ->and($byCode->get('0018')->first()->areaSourceId)->toBe('no:municipality:oslo');
});
