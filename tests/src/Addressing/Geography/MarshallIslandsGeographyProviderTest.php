<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('nests 24 municipalities under the 2 chains', function (): void {
    $areas = app(MarshallIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(2)
        ->and($areas->where('level', 2))->toHaveCount(24)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ralik'))->toHaveCount(14)
        ->and($areas->where('level', 2)->where('parentSourceId', 'mh:chain:ratak'))->toHaveCount(10)
        ->and($byId->get('mh:municipality:majuro')->parentSourceId)->toBe('mh:chain:ratak')
        ->and($byId->get('mh:municipality:kwajalein')->parentSourceId)->toBe('mh:chain:ralik');
});

it('bundles the 2 Marshall Islands ZIPs on the hub atolls', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('MH', $dir . '/marshall-islands-postal-codes.csv', $dir . '/marshall-islands-postal-code-areas.csv', 'aiarmada.addressing.marshall_islands');

    $postcodes = $source->postalCodes()->collect();

    // UPU mhl range + GeoNames MH localities: 96960 Majuro, 96970
    // Ebeye (Kwajalein); outer atolls route via the hubs.
    expect($postcodes)->toHaveCount(2);

    $byCode = $postcodes->keyBy->code;

    expect((string) $byCode->get('96960')->areaSourceId)->toBe('mh:municipality:majuro')
        ->and((string) $byCode->get('96970')->areaSourceId)->toBe('mh:municipality:kwajalein');
});
