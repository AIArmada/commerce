<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Benin\BeninGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Beninese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BJ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AL',
        'name' => 'Alibori (legacy)',
        'label' => 'Alibori (legacy)',
    ]);

    app(BeninGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Alibori')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Beninese state code to its area', function (): void {
    $mappings = app(BeninGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AL', 'AK', 'AQ', 'BO', 'CO', 'DO', 'KO', 'LI', 'MO', 'OU', 'PL', 'ZO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BeninGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Beninese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BJ');
    $country = AddressCountry::query()->where('iso2', 'BJ')->firstOrFail();

    expect($result['seeded'])->toContain('BJ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
