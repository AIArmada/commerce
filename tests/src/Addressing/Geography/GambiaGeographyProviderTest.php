<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Gambia\GambiaAddressFormatter;
use AIArmada\Addressing\Geography\Gambia\GambiaGeographyProvider;

it('formats Gambian addresses without a postcode system', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Liberation Avenue',
        'city' => 'BANJUL',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("21 Liberation Avenue\nBANJUL\nThe Gambia");
});
it('formats Gambian addresses with the region below the locality', function (): void {
    $formatted = app(GambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Main Street',
        'city' => 'Basse',
        'state' => 'Upper River',
        'country_code' => 'GM',
    ]));

    expect($formatted)->toBe("Main Street\nBasse\nUpper River\nThe Gambia");
});

it('ships 42 districts under regions with parent links', function (): void {
    $areas = app(GambiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(42)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gm:district:banjul-central')->name)->toBe('Banjul Central')
        ->and($byId->get('gm:district:basse-fulladu-east')->name)->toBe('Basse Fulladu East');
});

it('ships Kanifing as a first-level city alongside Banjul', function (): void {
    $areas = app(GambiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(7)
        ->and($byId->get('gm:city:kanifing')->name)->toBe('Kanifing')
        ->and($byId->get('gm:city:kanifing')->parentSourceId)->toBeNull();
});
