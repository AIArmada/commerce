<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Australia\AustraliaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 8 Australian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AU')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'NSW',
        'name' => 'New South',
        'label' => 'New South',
    ]);

    app(AustraliaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('New South Wales')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8);
});

it('maps every Australian state code to its area', function (): void {
    $mappings = app(AustraliaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(8)
        ->and(array_keys($mappings))->toBe(['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AustraliaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Australian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AU');
    $country = AddressCountry::query()->where('iso2', 'AU')->firstOrFail();

    expect($result['seeded'])->toContain('AU')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territory')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});
