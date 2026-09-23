<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Qatar\QatarAddressFormatter;
use AIArmada\Addressing\Geography\Qatar\QatarGeographyProvider;

it('formats Qatari addresses without a postcode line', function (): void {
    $formatted = app(QatarAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 3263',
        'city' => 'DOHA',
        'country_code' => 'QA',
    ]));

    expect($formatted)->toBe("P.O. Box 3263\nDOHA\nQatar");
});

it('ships 90 census zones under municipalities with parent links', function (): void {
    $areas = app(QatarGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $zones = $areas->where('type', 'zone');

    expect($zones)->toHaveCount(90)
        ->and($zones->pluck('parentSourceId')->every(fn ($p) => $p !== null && str_starts_with($p, 'qa:municipality:')))->toBeTrue()
        ->and($byId->get('qa:zone:doha:1')->code)->toBe('1')
        ->and($byId->get('qa:zone:doha:1')->parentSourceId)->toBe('qa:municipality:doha');
});
