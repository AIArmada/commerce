<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsAddressFormatter;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;

it('formats Dutch addresses with two spaces after the postcode', function (): void {
    $formatted = app(NetherlandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Drieslag 5-1',
        'city' => 'ARNHEM',
        'postcode' => '6832 am',
        'country_code' => 'NL',
    ]));

    expect($formatted)->toBe("Drieslag 5-1\n6832 AM  ARNHEM\nNetherlands");
});

it('ships 342 municipalities under provinces with parent links', function (): void {
    $areas = app(NetherlandsGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'municipality');

    expect($l2)->toHaveCount(342)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('nl:municipality:amsterdam')->name)->toBe('Amsterdam')
        ->and($byId->get('nl:municipality:rotterdam')->name)->toBe('Rotterdam')
        ->and($byId->get('nl:municipality:utrecht')->name)->toBe('Utrecht');
});

it('labels tiers Provincie and Gemeente', function (): void {
    $provider = app(NetherlandsGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Provincie', 'municipality' => 'Gemeente'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
