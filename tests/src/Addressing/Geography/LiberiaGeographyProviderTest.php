<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 15 Liberian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BM',
        'name' => 'Bomi (legacy)',
        'label' => 'Bomi (legacy)',
    ]);

    app(LiberiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bomi')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15);
});

it('maps every Liberian state code to its area', function (): void {
    $mappings = app(LiberiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(15)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BM', 'BG', 'GP', 'GB', 'CM', 'GG', 'GK', 'LO', 'MG', 'MY', 'MO', 'NI', 'RI', 'RG', 'SI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LiberiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Liberian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LR');
    $country = AddressCountry::query()->where('iso2', 'LR')->firstOrFail();

    expect($result['seeded'])->toContain('LR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(15)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);
});
