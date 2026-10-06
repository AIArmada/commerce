<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Croatia\CroatiaGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 21 counties with 128 towns and 428 municipalities under L1 parents', function (): void {
    $areas = app(CroatiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(577)
        ->and($l1)->toHaveCount(21)
        ->and($l2)->toHaveCount(556)
        ->and($l2->where('type', 'town'))->toHaveCount(128)
        ->and($l2->where('type', 'municipality'))->toHaveCount(428)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($l1->pluck('code')->sort()->values()->all())->toBe(array_map(fn ($i) => sprintf('%02d', $i), range(1, 21)))
        ->and($byId->get('hr:county:zagreb')->code)->toBe('01')
        ->and($byId->get('hr:county:city-of-zagreb')->code)->toBe('21')
        ->and($byId->get('hr:town:zagreb')->parentSourceId)->toBe('hr:county:city-of-zagreb');
});

it('pins the B20 verify-only postal pass: 1094 unanimous legs, 5 keeps, 2 held-out codes', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('HR', $dir . '/croatia-postal-codes.csv', $dir . '/croatia-postal-code-areas.csv', 'aiarmada.addressing.croatia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1094)
        ->and($postcodes)->toHaveCount(1094);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Anchors (county seats).
    expect($primary('10000'))->toBe('hr:county:city-of-zagreb')
        ->and($primary('21000'))->toBe('hr:county:split-dalmatia')
        ->and($primary('31000'))->toBe('hr:county:osijek-baranja')
        ->and($primary('51000'))->toBe('hr:county:primorje-gorski-kotar')
        ->and($primary('20000'))->toBe('hr:county:dubrovnik-neretva');

    // Hand-reviewed keeps: county legs beat GN/office quirks; city legs follow UPU office rule.
    expect($primary('10290'))->toBe('hr:county:zagreb')
        ->and($primary('10456'))->toBe('hr:county:zagreb')
        ->and($primary('10253'))->toBe('hr:county:city-of-zagreb')
        ->and($primary('10373'))->toBe('hr:county:city-of-zagreb')
        ->and($primary('10361'))->toBe('hr:county:city-of-zagreb')
        ->and($primary('53295'))->toBe('hr:county:lika-senj');

    // Box code present; held-out codes absent.
    expect($primary('10101'))->toBe('hr:county:city-of-zagreb')
        ->and($byCode->has('10004'))->toBeFalse()
        ->and($byCode->has('31200'))->toBeFalse();
});
