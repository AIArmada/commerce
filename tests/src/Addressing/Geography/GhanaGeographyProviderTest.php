<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Ghana\GhanaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 16 Ghanaian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GH')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AA',
        'name' => 'Accra',
        'label' => 'Accra',
    ]);

    app(GhanaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Greater Accra')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16);
});

it('maps every Ghanaian state code to its area', function (): void {
    $mappings = app(GhanaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(16)
        ->and(array_keys($mappings))->toBe(['AA', 'AF', 'AH', 'BE', 'BO', 'CP', 'EP', 'NE', 'NP', 'OT', 'SV', 'TV', 'UE', 'UW', 'WN', 'WP']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GhanaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ghanaian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GH');
    $country = AddressCountry::query()->where('iso2', 'GH')->firstOrFail();

    expect($result['seeded'])->toContain('GH')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});
