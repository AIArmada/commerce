<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;

it('formats Burundian addresses without a postcode system', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'state' => 'Bujumbura',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\nBujumbura\nBurundi");
});
it('prints any supplied Burundian code on its own line', function (): void {
    $formatted = app(BurundiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 1915',
        'city' => 'MUKAZA',
        'postcode' => '99999',
        'country_code' => 'BI',
    ]));

    expect($formatted)->toBe("BP 1915\nMUKAZA\n99999\nBurundi");
});

it('ships 42 communes under provinces with parent links', function (): void {
    $areas = app(BurundiGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(42)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bi:commune:karusi')->name)->toBe('Karusi')
        ->and($byId->get('bi:commune:shombo')->name)->toBe('Shombo')
        ->and($byId->get('bi:commune:mukaza')->name)->toBe('Mukaza');
});

it('labels tiers Province and Commune', function (): void {
    $provider = app(BurundiGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Province', 'commune' => 'Commune'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
