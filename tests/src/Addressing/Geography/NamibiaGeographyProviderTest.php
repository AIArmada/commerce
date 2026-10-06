<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Namibia\NamibiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 14 regions with ISO codes and the gazetted ǁKaras name', function (): void {
    $areas = app(NamibiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'region'))->toHaveCount(14)
        // GG5261 renames KARAS to !KARAS (ǁ clicks); ISO 3166-2:NA lists ǁKaras.
        ->and($byId->get('na:region:karas')->name)->toBe('ǁKaras')
        ->and($byId->get('na:region:karas')->code)->toBe('KA')
        ->and($byId->get('na:region:zambezi')->code)->toBe('CA');
});

it('ships 121 constituencies with gazette spellings and parents', function (): void {
    $areas = app(NamibiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(121)
        ->and($l2->where('parentSourceId', 'na:region:ohangwena'))->toHaveCount(12)
        ->and($l2->where('parentSourceId', 'na:region:kavango-west'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'na:region:kavango-east'))->toHaveCount(6)
        // GG5261 spells Okarukambe (Steinhausen rename); WP/citypopulation err.
        ->and($byId->get('na:constituency:okorukambe')->name)->toBe('Okarukambe')
        // WP list-table omissions, confirmed via ECN register + GG5261.
        ->and($byId->get('na:constituency:tondoro')->parentSourceId)->toBe('na:region:kavango-west')
        ->and($byId->get('na:constituency:oshikunde')->parentSourceId)->toBe('na:region:ohangwena')
        // Diacritic/click names kept per WP + citypopulation (ECN folds ASCII).
        ->and($byId->get('na:constituency:daures')->name)->toBe('Dâures')
        ->and($byId->get('na:constituency:naminus')->name)->toBe('ǃNamiǂNûs')
        ->and($byId->get('na:constituency:moses-garoeb')->name)->toBe('Moses ǁGaroëb');
});

it('links all 149 NamPost offices at region level by prefix rule', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NA', $dir . '/namibia-postal-codes.csv', $dir . '/namibia-postal-code-areas.csv', 'aiarmada.addressing.namibia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(149)
        ->and($postcodes->where('isPrimary', true))->toHaveCount(149);

    $byCode = $postcodes->keyBy->code;

    // First-two-digits = region per the NamPost poster.
    expect($byCode->get('10005')->areaSourceId)->toBe('na:region:khomas')
        ->and($byCode->get('17001')->areaSourceId)->toBe('na:region:ohangwena')
        ->and($byCode->get('18001')->areaSourceId)->toBe('na:region:kavango-west')
        ->and($byCode->get('19001')->areaSourceId)->toBe('na:region:kavango-east')
        ->and($byCode->get('23017')->areaSourceId)->toBe('na:region:karas');
});
