<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 12 provinces with 342 municipalities under province parents', function (): void {
    $areas = app(NetherlandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l1 = $areas->where('level', 1);
    $l2 = $areas->where('level', 2);

    expect($areas)->toHaveCount(354)
        ->and($l1)->toHaveCount(12)
        ->and($l2)->toHaveCount(342)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue();

    expect($byId->get('nl:province:drenthe')->code)->toBe('DR')
        ->and($byId->get('nl:province:limburg')->code)->toBe('LI')
        ->and($byId->get('nl:province:zuid-holland')->code)->toBe('ZH')
        ->and($byId->get('nl:municipality:beekdaelen')->parentSourceId)->toBe('nl:province:limburg')
        ->and($byId->get('nl:municipality:rozendaal')->parentSourceId)->toBe('nl:province:gelderland');
});

it('pins the B18 BAG pass: 4071 PC4 codes with the DEL + 2 ADDs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NL', $dir . '/netherlands-postal-codes.csv', $dir . '/netherlands-postal-code-areas.csv', 'aiarmada.addressing.netherlands');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(4071)
        ->and($postcodes)->toHaveCount(4092);

    // 1216 keeps its Hilversum primary; the Wijdemeren secondary is gone.
    expect($byCode->get('1216')->where('isPrimary', true)->first()->areaSourceId)->toBe('nl:municipality:hilversum')
        ->and($byCode->get('1216')->pluck('areaSourceId')->contains('nl:municipality:wijdemeren'))->toBeFalse();

    // Added BAG-attested secondaries.
    expect($byCode->get('6153')->pluck('areaSourceId')->contains('nl:municipality:beekdaelen'))->toBeTrue()
        ->and($byCode->get('6881')->pluck('areaSourceId')->contains('nl:municipality:rozendaal'))->toBeTrue();
});
