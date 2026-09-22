<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsAddressFormatter;
use AIArmada\Addressing\Geography\SolomonIslands\SolomonIslandsGeographyProvider;

it('formats Solomon Islands addresses without a postcode system', function (): void {
    $formatted = app(SolomonIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Honiara',
        'country_code' => 'SB',
    ]));

    expect($formatted)->toBe("PO Box 1\nHoniara\nSolomon Islands");
});

it('prints any supplied Honiara code on its own line', function (): void {
    $formatted = app(SolomonIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Honiara',
        'postcode' => '99999',
        'country_code' => 'SB',
    ]));

    expect($formatted)->toBe("PO Box 1\nHoniara\n99999\nSolomon Islands");
});

it('ships 183 wards under provinces with parent links', function (): void {
    $areas = app(SolomonIslandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'ward');

    expect($l2)->toHaveCount(183)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('sb:ward:nggosi')->code)->toBe('SB1010431001')
        ->and($byId->get('sb:ward:rove-lengakiki')->name)->toBe('Rove - Lengakiki')
        ->and($byId->get('sb:ward:vonunu')->name)->toBe('Vonunu');
});
