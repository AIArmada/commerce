<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Aland tree of 16 municipalities', function (): void {
    $areas = app(AlandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(16)
        ->and($areas->where('level', 1))->toHaveCount(16);

    // 01-16 synthetic. Names 16/16 vs Municipalities of Aland +
    // GeoNames AX admin2.
    expect($byId->get('ax:municipality:mariehamn')->code)->toBe('01')
        ->and($byId->get('ax:municipality:jomala')->code)->toBe('02')
        ->and($byId->get('ax:municipality:finstrom')->code)->toBe('03')
        ->and($byId->get('ax:municipality:brando')->name)->toBe('Brändö')
        ->and($byId->get('ax:municipality:sottunga')->code)->toBe('16');
});

it('bundles the 33 Aland street postcodes as single municipality primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('AX', $dir . '/aland-postal-codes.csv', $dir . '/aland-postal-code-areas.csv', 'aiarmada.addressing.aland');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(33)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // GeoNames AX dump + Aland Post directory, exact (AX- prefix per
    // the UPU ala profile). 22110/22120/22140 localities read
    // MARIEHAMN but sit in Jomala (GN admin2 + AP village notes).
    expect((string) $byCode->get('AX-22100')->areaSourceId)->toBe('ax:municipality:mariehamn')
        ->and((string) $byCode->get('AX-22110')->areaSourceId)->toBe('ax:municipality:jomala')
        ->and((string) $byCode->get('AX-22160')->areaSourceId)->toBe('ax:municipality:lemland')
        ->and((string) $byCode->get('AX-22410')->areaSourceId)->toBe('ax:municipality:finstrom')
        ->and((string) $byCode->get('AX-22950')->areaSourceId)->toBe('ax:municipality:brando');

    // Held out: PO Box twins (AP postboxar labels + UPU fin ends-in-1
    // rule; 22151 GN-only, 22271 PostNord-only).
    expect($byCode->has('AX-22101'))->toBeFalse()
        ->and($byCode->has('AX-22111'))->toBeFalse()
        ->and($byCode->has('AX-22151'))->toBeFalse()
        ->and($byCode->has('AX-22271'))->toBeFalse()
        ->and($byCode->has('AX-22411'))->toBeFalse();
});
