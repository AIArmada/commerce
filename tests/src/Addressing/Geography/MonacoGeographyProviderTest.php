<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Monaco\MonacoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('lists only the general-delivery postcode with no quarter links', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MC', $dir . '/monaco-postal-codes.csv', $dir . '/monaco-postal-code-areas.csv', 'test');

    $rows = $source->postalCodes()->collect();

    // Per the UPU Monaco sheet, 98000 covers all physical delivery while
    // 98001+ are institutional/CEDEX codes, so no quarter routing exists.
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->code)->toBe('98000')
        ->and($rows->first()->areaSourceId)->toBeNull();
});

it('keeps the 17 ISO quarters rather than the 2013 ordinance wards', function (): void {
    $areas = app(MonacoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(17)
        ->and($areas->firstWhere('sourceId', 'mc:quarter:la-colle')->code)->toBe('CL');
});

it('pins the verified Monaco ISO quarter codes', function (): void {
    $areas = app(MonacoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // ISO 3166-2:MC codes, exact set (B5 re-verified).
    expect($byId->get('mc:quarter:fontvieille')->code)->toBe('FO')
        ->and($byId->get('mc:quarter:monaco-ville')->code)->toBe('MO')
        ->and($byId->get('mc:quarter:monte-carlo')->code)->toBe('MC')
        ->and($byId->get('mc:quarter:saint-roman')->code)->toBe('SR')
        ->and($byId->get('mc:quarter:sainte-devote')->code)->toBe('SD')
        ->and($byId->get('mc:quarter:vallon-de-la-rousse')->code)->toBe('VR');
});
