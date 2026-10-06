<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Montserrat tree of 3 parishes', function (): void {
    $areas = app(MontserratGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(3)
        ->and($areas->where('level', 1))->toHaveCount(3);

    // No ISO 3166-2:MS codes exist (divisions "not relevant"); 01-03 synthetic.
    // B1 dropped phantom Saint Patrick: Saint Patrick's is a destroyed
    // village (GeoNames PPLW), and Plymouth sits in Saint Anthony.
    expect($byId->has('ms:parish:saint-patrick'))->toBeFalse()
        ->and($byId->get('ms:parish:saint-peter')->code)->toBe('01')
        ->and($byId->get('ms:parish:saint-georges')->code)->toBe('02')
        ->and($byId->get('ms:parish:saint-anthony')->code)->toBe('03')
        ->and($byId->get('ms:parish:saint-georges')->name)->toBe('Saint Georges');
});

it('bundles the 8 Montserrat sub-post-office codes as Saint Peter primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MS', $dir . '/montserrat-postal-codes.csv', $dir . '/montserrat-postal-code-areas.csv', 'aiarmada.addressing.montserrat');

    $postcodes = $source->postalCodes()->collect();

    // UPU msrEn: first digit is the parish; all 8 codes are 1xxx.
    expect($postcodes)->toHaveCount(8)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    expect((string) $byCode->get('MSR1110')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1120')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1210')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1230')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1250')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1310')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1330')->areaSourceId)->toBe('ms:parish:saint-peter')
        ->and((string) $byCode->get('MSR1350')->areaSourceId)->toBe('ms:parish:saint-peter');
});
