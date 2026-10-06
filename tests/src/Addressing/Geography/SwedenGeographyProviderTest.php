<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('names the 1480 municipality Göteborg per SCB', function (): void {
    $areas = app(SwedenGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('se:municipality:gothenburg')->name)->toBe('Göteborg')
        ->and($areas->get('se:municipality:gothenburg')->code)->toBe('1480');
});

it('links the B17 re-attached postort blocks to their home municipalities', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SE', $dir . '/sweden-postal-codes.csv', $dir . '/sweden-postal-code-areas.csv', 'aiarmada.addressing.sweden');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(18887);

    $byCode = $postcodes->groupBy->code;

    // Kisa / Rimforsa / Horn postorts sit in Kinda kommun, not Linköping/Västervik.
    foreach (['590 36', '590 40', '590 41', '590 42', '590 46'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('se:municipality:kinda');
    }

    // Storvreta is Uppsala's third largest tätort, not Sala's.
    foreach (['743 01', '743 21', '743 40'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('se:municipality:uppsala');
    }

    // Höör, Vintrosa, Hållnäs, Rockneby/Läckeby, Stugun, Ydre blocks.
    expect($byCode->get('243 01')->first()->areaSourceId)->toBe('se:municipality:hoor')
        ->and($byCode->get('243 96')->first()->areaSourceId)->toBe('se:municipality:hoor')
        ->and($byCode->get('719 21')->first()->areaSourceId)->toBe('se:municipality:orebro')
        ->and($byCode->get('719 95')->first()->areaSourceId)->toBe('se:municipality:orebro')
        ->and($byCode->get('819 63')->first()->areaSourceId)->toBe('se:municipality:tierp')
        ->and($byCode->get('380 30')->first()->areaSourceId)->toBe('se:municipality:kalmar')
        ->and($byCode->get('380 31')->first()->areaSourceId)->toBe('se:municipality:kalmar')
        ->and($byCode->get('830 76')->first()->areaSourceId)->toBe('se:municipality:ragunda')
        ->and($byCode->get('573 74')->first()->areaSourceId)->toBe('se:municipality:ydre');
});
