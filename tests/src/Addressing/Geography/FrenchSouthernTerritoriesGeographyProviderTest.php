<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesAddressFormatter;
use AIArmada\Addressing\Geography\FrenchSouthernTerritories\FrenchSouthernTerritoriesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FrenchSouthernTerritoriesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the French Southern Territories tree with state links', function (): void {
    $this->seedCountry('TF');

    $result = app(SeedCountryGeographiesAction::class)->execute('TF');
    $country = AddressCountry::query()->where('iso2', 'TF')->firstOrFail();

    expect($result['seeded'])->toContain('TF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats French Southern Territories addresses without a postcode system', function (): void {
    $formatted = app(FrenchSouthernTerritoriesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'country_code' => 'TF',
    ]));

    expect($formatted)->toBe("Base Alfred Faure\nPort-aux-Français\nFrench Southern Territories");
});

it('prints any supplied Crozet code on its own line', function (): void {
    $formatted = app(FrenchSouthernTerritoriesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Base Alfred Faure',
        'city' => 'Port-aux-Français',
        'postcode' => '98400',
        'country_code' => 'TF',
    ]));

    expect($formatted)->toBe("Base Alfred Faure\nPort-aux-Français\n98400\nFrench Southern Territories");
});
