<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Greece\GreeceAddressFormatter;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;

it('formats Greek addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => '1, D. GOUNARI STREET',
        'city' => 'MAROUSI',
        'postcode' => '151 24',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("1, D. GOUNARI STREET\n151 24 MAROUSI\nGreece");
});
it('formats Greek post box addresses with the box postcode', function (): void {
    $formatted = app(GreeceAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. BOX 999',
        'city' => 'MAROUSI',
        'postcode' => '151 10',
        'country_code' => 'GR',
    ]));

    expect($formatted)->toBe("P.O. BOX 999\n151 10 MAROUSI\nGreece");
});

it('ships 332 municipalities under administrative regions with parent links', function (): void {
    $areas = app(GreeceGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(332)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('gr:municipality:athens')->name)->toBe('Athens')
        ->and($byId->get('gr:municipality:thessaloniki')->name)->toBe('Thessaloniki')
        ->and($byId->get('gr:municipality:patras')->name)->toBe('Patras');
});

it('labels tiers Periféreia and Dímos', function (): void {
    $provider = app(GreeceGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['administrative_region' => 'Periféreia', 'municipality' => 'Dímos'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
