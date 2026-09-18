<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 20 Kazakh states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'KZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '10',
        'name' => 'Abai (legacy)',
        'label' => 'Abai (legacy)',
    ]);

    app(KazakhstanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Abai')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20);
});

it('maps every Kazakh state code to its area', function (): void {
    $mappings = app(KazakhstanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(20)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['10', '11', '15', '19', '75', '71', '23', '63', '31', '33', '35', '39', '43', '47', '59', '55', '79', '61', '62', '27']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KazakhstanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kazakh tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('KZ');
    $country = AddressCountry::query()->where('iso2', 'KZ')->firstOrFail();

    expect($result['seeded'])->toContain('KZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});
