<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Burundi\BurundiAddressFormatter;
use AIArmada\Addressing\Geography\Burundi\BurundiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BurundiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Burundian tree with state links', function (): void {
    $this->seedCountry('BI');

    $result = app(SeedCountryGeographiesAction::class)->execute('BI');
    $country = AddressCountry::query()->where('iso2', 'BI')->firstOrFail();

    expect($result['seeded'])->toContain('BI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

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
