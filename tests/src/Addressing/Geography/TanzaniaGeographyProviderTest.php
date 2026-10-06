<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Tanzania\TanzaniaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships the B16 NBS-2022 council structure with renamed and dissolved districts', function (): void {
    $areas = app(TanzaniaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas)->toHaveCount(225)
        ->and($areas->where('type', 'district'))->toHaveCount(194)
        ->and($byId->get('tz:district:mlimba')->parentSourceId)->toBe('tz:region:morogoro')
        ->and($byId->get('tz:district:mtama')->parentSourceId)->toBe('tz:region:lindi')
        ->and($byId->get('tz:district:kibiti')->parentSourceId)->toBe('tz:region:pwani')
        ->and($byId->get('tz:district:tanganyika')->name)->toBe('Tanganyika')
        ->and($byId->has('tz:district:kilombero'))->toBeFalse()
        ->and($byId->has('tz:district:lindi'))->toBeFalse()
        ->and($byId->has('tz:district:mpanda'))->toBeFalse();
});

it('links 4096 postcodes with the B16 Zanzibar rebuild and council remaps', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('TZ', $dir . '/tanzania-postal-codes.csv', $dir . '/tanzania-postal-code-areas.csv', 'aiarmada.addressing.tanzania');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(4096);

    $byCode = $postcodes->groupBy->code;

    // Phantom sequential extensions + mainland phantoms + swapped-off code excluded.
    foreach (['57231', '54118', '53733', '71125', '71224', '71314', '72113', '72215', '73117', '73216', '74123', '74221', '75121', '75213'] as $bad) {
        expect($byCode->has($bad))->toBeFalse();
    }

    $primary = fn (string $code): string => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Katavi 502 block, Kibiti block, Lituta fill, Njisi swap, Mpimbwe fixes.
    expect($primary('50201'))->toBe('tz:district:tanganyika')
        ->and($primary('61801'))->toBe('tz:district:kibiti')
        ->and($primary('61617'))->toBe('tz:district:kibiti')
        ->and($primary('57731'))->toBe('tz:district:madaba')
        ->and($primary('73733'))->toBe('tz:district:kyela')
        ->and($primary('50315'))->toBe('tz:district:mpimbwe')
        ->and($primary('65201'))->toBe('tz:district:mtama')
        ->and($primary('67510'))->toBe('tz:district:mlimba')
        ->and($primary('65110'))->toBe('tz:district:lindi-city')
        ->and($primary('71101'))->toBe('tz:district:zanzibar-city')
        ->and($primary('75208'))->toBe('tz:district:micheweni')
        ->and($primary('71120'))->toBe('tz:district:zanzibar-city');
});
