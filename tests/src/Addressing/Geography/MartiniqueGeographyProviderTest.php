<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('pins the verified Martinique tree of 4 districts and 34 communes', function (): void {
    $areas = app(MartiniqueGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(4)
        ->and($areas->where('type', 'commune'))->toHaveCount(34)
        ->and($byId->get('mq:commune:fort-de-france')->code)->toBe('97209')
        ->and($byId->get('mq:commune:saint-pierre')->code)->toBe('97225')
        ->and($byId->get('mq:commune:bellefontaine')->code)->toBe('97234');
});

it('bundles the 30 Martinique postcodes with exact Hexasmal legs', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MQ', $dir . '/martinique-postal-codes.csv', $dir . '/martinique-postal-code-areas.csv', 'aiarmada.addressing.martinique');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(35);

    $byCode = $postcodes->groupBy->code;

    // Hexasmal distinct legs + GeoNames MQ.txt 30/30, exact.
    // Shared codes: 97218 x3, 97222 x2, 97250 x3. Primaries
    // kept per the stability rule (Hexasmal defines no primary).
    expect($byCode->get('97218'))->toHaveCount(3)
        ->and($byCode->get('97222'))->toHaveCount(2)
        ->and($byCode->get('97250'))->toHaveCount(3);

    $legs = static fn (string $code): array => $byCode->get($code)
        ->mapWithKeys(static fn ($row): array => [(string) $row->areaSourceId => $row->isPrimary])
        ->all();

    expect($legs('97218'))->toBe(['mq:commune:basse-pointe' => true, 'mq:commune:grand-riviere' => false, 'mq:commune:macouba' => false])
        ->and($legs('97222'))->toBe(['mq:commune:bellefontaine' => true, 'mq:commune:case-pilote' => false])
        ->and($legs('97250'))->toBe(['mq:commune:fonds-saint-denis' => false, 'mq:commune:le-precheur' => false, 'mq:commune:saint-pierre' => true])
        ->and($legs('97200'))->toBe(['mq:commune:fort-de-france' => true]);
});
