<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Andorra tree of 7 parishes', function (): void {
    $areas = app(AndorraGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(7)
        ->and($areas->where('level', 1))->toHaveCount(7);

    // ISO 3166-2:AD codes, exact set.
    expect($byId->get('ad:parish:canillo')->code)->toBe('02')
        ->and($byId->get('ad:parish:encamp')->code)->toBe('03')
        ->and($byId->get('ad:parish:ordino')->code)->toBe('05')
        ->and($byId->get('ad:parish:la-massana')->code)->toBe('04')
        ->and($byId->get('ad:parish:andorra-la-vella')->code)->toBe('07')
        ->and($byId->get('ad:parish:sant-julia-de-loria')->code)->toBe('06')
        ->and($byId->get('ad:parish:escaldes-engordany')->code)->toBe('08');
});

it('bundles the 7 Andorran parish postcodes as single primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AD', $dir . '/andorra-postal-codes.csv', $dir . '/andorra-postal-code-areas.csv', 'aiarmada.addressing.andorra');

    $postcodes = $source->postalCodes()->collect();

    // GeoNames AD dump (7/7 with admin1) + UPU AD700 anchor.
    expect($postcodes)->toHaveCount(7)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    expect((string) $byCode->get('AD100')->areaSourceId)->toBe('ad:parish:canillo')
        ->and((string) $byCode->get('AD200')->areaSourceId)->toBe('ad:parish:encamp')
        ->and((string) $byCode->get('AD300')->areaSourceId)->toBe('ad:parish:ordino')
        ->and((string) $byCode->get('AD400')->areaSourceId)->toBe('ad:parish:la-massana')
        ->and((string) $byCode->get('AD500')->areaSourceId)->toBe('ad:parish:andorra-la-vella')
        ->and((string) $byCode->get('AD600')->areaSourceId)->toBe('ad:parish:sant-julia-de-loria')
        ->and((string) $byCode->get('AD700')->areaSourceId)->toBe('ad:parish:escaldes-engordany');
});
