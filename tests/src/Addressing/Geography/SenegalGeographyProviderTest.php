<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Senegal\SenegalGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Senegalese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'DK',
        'name' => 'Dakar (legacy)',
        'label' => 'Dakar (legacy)',
    ]);

    app(SenegalGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Dakar')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14);
});

it('maps every Senegalese state code to its area', function (): void {
    $mappings = app(SenegalGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['DK', 'DB', 'FK', 'KA', 'KL', 'KE', 'KD', 'LG', 'MT', 'SL', 'SE', 'TC', 'TH', 'ZG']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SenegalGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Senegalese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SN');
    $country = AddressCountry::query()->where('iso2', 'SN')->firstOrFail();

    expect($result['seeded'])->toContain('SN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
