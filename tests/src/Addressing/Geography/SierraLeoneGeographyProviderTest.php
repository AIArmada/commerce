<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SierraLeone\SierraLeoneGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 5 Sierra Leonean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'E',
        'name' => 'Eastern (legacy)',
        'label' => 'Eastern (legacy)',
    ]);

    app(SierraLeoneGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Eastern')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5);
});

it('maps every Sierra Leonean state code to its area', function (): void {
    $mappings = app(SierraLeoneGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(5)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['E', 'NW', 'N', 'S', 'W']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SierraLeoneGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sierra Leonean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SL');
    $country = AddressCountry::query()->where('iso2', 'SL')->firstOrFail();

    expect($result['seeded'])->toContain('SL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'area')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});
