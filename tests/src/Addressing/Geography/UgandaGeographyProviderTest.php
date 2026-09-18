<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Uganda\UgandaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 4 Ugandan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'UG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'C',
        'name' => 'Central Region',
        'label' => 'Central Region',
    ]);

    app(UgandaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4);
});

it('maps every Ugandan state code to its area', function (): void {
    $mappings = app(UgandaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(4)
        ->and(array_keys($mappings))->toBe(['C', 'E', 'N', 'W']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UgandaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ugandan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('UG');
    $country = AddressCountry::query()->where('iso2', 'UG')->firstOrFail();

    expect($result['seeded'])->toContain('UG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});
