<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified San Marino tree of 9 castelli', function (): void {
    $areas = app(SanMarinoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'municipality'))->toHaveCount(9)
        ->and($areas->where('level', 1))->toHaveCount(9);

    // ISO 3166-2:SM codes; SM-07 Città di San Marino ships as
    // San Marino.
    expect($byId->get('sm:municipality:acquaviva')->code)->toBe('01')
        ->and($byId->get('sm:municipality:chiesanuova')->code)->toBe('02')
        ->and($byId->get('sm:municipality:domagnano')->code)->toBe('03')
        ->and($byId->get('sm:municipality:faetano')->code)->toBe('04')
        ->and($byId->get('sm:municipality:fiorentino')->code)->toBe('05')
        ->and($byId->get('sm:municipality:borgo-maggiore')->code)->toBe('06')
        ->and($byId->get('sm:municipality:san-marino')->code)->toBe('07')
        ->and($byId->get('sm:municipality:montegiardino')->code)->toBe('08')
        ->and($byId->get('sm:municipality:serravalle')->code)->toBe('09');
});

it('bundles the 10 Sammarinese postcodes as municipality primaries', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('SM', $dir . '/san-marino-postal-codes.csv', $dir . '/san-marino-postal-code-areas.csv', 'aiarmada.addressing.san_marino');

    $postcodes = $source->postalCodes()->collect();

    // UPU smrEn full locality list; Serravalle holds 47891+47899.
    expect($postcodes)->toHaveCount(10)
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    expect((string) $byCode->get('47890')->areaSourceId)->toBe('sm:municipality:san-marino')
        ->and((string) $byCode->get('47891')->areaSourceId)->toBe('sm:municipality:serravalle')
        ->and((string) $byCode->get('47892')->areaSourceId)->toBe('sm:municipality:acquaviva')
        ->and((string) $byCode->get('47893')->areaSourceId)->toBe('sm:municipality:borgo-maggiore')
        ->and((string) $byCode->get('47894')->areaSourceId)->toBe('sm:municipality:chiesanuova')
        ->and((string) $byCode->get('47895')->areaSourceId)->toBe('sm:municipality:domagnano')
        ->and((string) $byCode->get('47896')->areaSourceId)->toBe('sm:municipality:faetano')
        ->and((string) $byCode->get('47897')->areaSourceId)->toBe('sm:municipality:fiorentino')
        ->and((string) $byCode->get('47898')->areaSourceId)->toBe('sm:municipality:montegiardino')
        ->and((string) $byCode->get('47899')->areaSourceId)->toBe('sm:municipality:serravalle');
});
