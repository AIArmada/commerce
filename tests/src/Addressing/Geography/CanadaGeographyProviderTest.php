<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 13 Canadian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'QC',
        'name' => 'Quebec City',
        'label' => 'Quebec City',
    ]);

    app(CanadaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Quebec')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13);
});

it('maps every Canadian state code to its area', function (): void {
    $mappings = app(CanadaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(13)
        ->and(array_keys($mappings))->toBe(['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CanadaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Canadian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CA');
    $country = AddressCountry::query()->where('iso2', 'CA')->firstOrFail();

    expect($result['seeded'])->toContain('CA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territory')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});
