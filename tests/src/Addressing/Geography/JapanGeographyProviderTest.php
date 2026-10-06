<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('types municipalities by kind from the kanji suffix', function (): void {
    $areas = app(JapanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'city'))->toHaveCount(792)
        ->and($areas->where('type', 'town'))->toHaveCount(743)
        ->and($areas->where('type', 'village'))->toHaveCount(189)
        ->and($areas->where('type', 'ward'))->toHaveCount(23)
        ->and($byId->get('jp:municipality:13101')->type)->toBe('ward')
        ->and($byId->get('jp:municipality:10523')->type)->toBe('town')
        ->and($byId->get('jp:municipality:01695')->type)->toBe('village');
});

it('pins the B21 postal pass: Tenryu-ku remaps, phantom drop, 38 KEN_ALL adds', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('JP', $dir . '/japan-postal-codes.csv', $dir . '/japan-postal-code-areas.csv', 'aiarmada.addressing.japan');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(120720)
        ->and($postcodes)->toHaveCount(120801);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('431-3301'))->toBe('jp:municipality:22130')
        ->and($primary('431-3901'))->toBe('jp:municipality:22130')
        ->and($primary('437-0601'))->toBe('jp:municipality:22130')
        ->and($byCode->get('432-0000'))->toHaveCount(1)
        ->and($primary('351-0008'))->toBe('jp:municipality:11227')
        ->and($primary('811-2312'))->toBe('jp:municipality:40349')
        ->and($primary('959-1001'))->toBe('jp:municipality:15218')
        ->and($primary('959-1047'))->toBe('jp:municipality:15218')
        ->and($primary('431-4121'))->toBe('jp:municipality:23563');
});
