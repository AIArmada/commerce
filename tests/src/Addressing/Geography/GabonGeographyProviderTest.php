<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 9 Gabonese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '1',
        'name' => 'Estuaire (legacy)',
        'label' => 'Estuaire (legacy)',
    ]);

    app(GabonGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Estuaire')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9);
});

it('maps every Gabonese state code to its area', function (): void {
    $mappings = app(GabonGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(9)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['1', '2', '3', '4', '5', '6', '7', '8', '9']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GabonGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Gabonese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GA');
    $country = AddressCountry::query()->where('iso2', 'GA')->firstOrFail();

    expect($result['seeded'])->toContain('GA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});
