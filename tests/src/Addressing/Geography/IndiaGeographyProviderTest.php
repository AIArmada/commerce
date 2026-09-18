<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\India\IndiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 36 Indian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'DL',
        'name' => 'Del',
        'label' => 'Del',
    ]);

    app(IndiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Delhi')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(36);
});

it('maps every Indian state code to its area', function (): void {
    $mappings = app(IndiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(36)
        ->and(array_keys($mappings))->toBe(['AN', 'AP', 'AR', 'AS', 'BR', 'CH', 'CT', 'DH', 'DL', 'GA', 'GJ', 'HP', 'HR', 'JH', 'JK', 'KA', 'KL', 'LA', 'LD', 'MH', 'ML', 'MN', 'MP', 'MZ', 'NL', 'OR', 'PB', 'PY', 'RJ', 'SK', 'TG', 'TN', 'TR', 'UK', 'UP', 'WB']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IndiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Indian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IN');
    $country = AddressCountry::query()->where('iso2', 'IN')->firstOrFail();

    expect($result['seeded'])->toContain('IN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(36)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'union_territory')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(36);
});
