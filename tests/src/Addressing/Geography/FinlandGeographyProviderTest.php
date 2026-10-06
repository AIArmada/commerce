<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Finland\FinlandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 292 mainland municipalities with verified regions and city types', function (): void {
    $areas = app(FinlandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(310)
        ->and($areas->get('fi:region:uusimaa')->code)->toBe('18')
        ->and($areas->get('fi:city:helsinki')->code)->toBe('091')
        ->and($areas->get('fi:city:helsinki')->parentSourceId)->toBe('fi:region:uusimaa')
        ->and($areas->get('fi:municipality:koski-tl')->name)->toBe('Koski Tl')
        ->and($areas->get('fi:city:mantta-vilppula')->code)->toBe('508')
        ->and($areas->get('fi:municipality:pedersoren-kunta')->code)->toBe('599')
        ->and($areas->has('fi:municipality:pertunmaa'))->toBeFalse()
        ->and($areas->has('fi:municipality:honkajoki'))->toBeFalse()
        ->and($areas->has('fi:municipality:valtimo'))->toBeFalse();
});

it('pins the B17-verified 00002 fix and post-merger legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('FI', $dir . '/finland-postal-codes.csv', $dir . '/finland-postal-code-areas.csv', 'aiarmada.addressing.finland');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(3576);

    $byCode = $postcodes->groupBy->code;

    // 00002 is Posti's own FI-00002 Helsinki (GN Hattula row is inconsistent).
    expect($byCode->get('00002')->first()->areaSourceId)->toBe('fi:city:helsinki')
        ->and($byCode->get('00100')->first()->areaSourceId)->toBe('fi:city:helsinki');

    // Merged municipalities keep old codes under successor legs.
    foreach (['19410', '19430', '19480'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('fi:municipality:mantyharju');
    }
    foreach (['38920', '38970'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('fi:city:kankaanpaa');
    }
    foreach (['75700', '75840'] as $code) {
        expect($byCode->get($code)->first()->areaSourceId)->toBe('fi:city:nurmes');
    }
});
