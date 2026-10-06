<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('types all 78 municipios as municipality with FIPS codes', function (): void {
    $areas = app(PuertoRicoGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(78)
        ->and($areas->where('type', 'region'))->toBeEmpty()
        ->and($areas->get('pr:municipality:arecibo')->code)->toBe('013')
        ->and($areas->get('pr:municipality:bayamon')->name)->toBe('Bayamón')
        ->and($areas->get('pr:municipality:bayamon')->code)->toBe('021')
        ->and($areas->get('pr:municipality:caguas')->code)->toBe('025')
        ->and($areas->get('pr:municipality:carolina')->code)->toBe('031')
        ->and($areas->get('pr:municipality:guaynabo')->code)->toBe('061')
        ->and($areas->get('pr:municipality:mayaguez')->code)->toBe('097')
        ->and($areas->get('pr:municipality:ponce')->code)->toBe('113')
        ->and($areas->get('pr:municipality:san-juan')->code)->toBe('127')
        ->and($areas->get('pr:municipality:toa-baja')->code)->toBe('137')
        ->and($areas->get('pr:municipality:trujillo-alto')->code)->toBe('139')
        ->and($areas->has('pr:region:arecibo'))->toBeFalse();

    $mappings = app(PuertoRicoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(78);

    foreach (['AR', 'BY', 'CG', 'CL', 'GN', 'MG', 'PO', 'SJ', 'TB', 'TA'] as $retiredCode) {
        expect($mappings)->not->toHaveKey($retiredCode);
    }
});

it('ships 901 barrios under municipios with parent links', function (): void {
    $areas = app(PuertoRicoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(901)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'barrio'))->toHaveCount(827)
        ->and($l2->where('type', 'barrio_pueblo'))->toHaveCount(74)
        ->and($byId->get('pr:barrio:santurce')->parentSourceId)->toBe('pr:municipality:san-juan')
        ->and($byId->get('pr:barrio_pueblo:adjuntas')->parentSourceId)->toBe('pr:municipality:adjuntas');
});

it('pins the B20 verify-only postal pass: 177 ZIPs with GN-matching legs, 00938 held', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('PR', $dir . '/puerto-rico-postal-codes.csv', $dir . '/puerto-rico-postal-code-areas.csv', 'aiarmada.addressing.puerto-rico');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(177)
        ->and($postcodes)->toHaveCount(177);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    // Notable assignments consistent with GN municipio.
    expect($primary('00968'))->toBe('pr:municipality:san-juan')
        ->and($primary('00934'))->toBe('pr:municipality:bayamon')
        ->and($primary('00785'))->toBe('pr:municipality:guayanilla')
        ->and($primary('00742'))->toBe('pr:municipality:ceiba')
        // Deep-dived keeps: dir-A index omissions proven stale artifacts.
        ->and($primary('00636'))->toBe('pr:municipality:san-german')
        ->and($primary('00930'))->toBe('pr:municipality:san-juan')
        // H1: split evidence, kept pending a human USPS-finder lookup.
        ->and($primary('00938'))->toBe('pr:municipality:san-juan');
});
