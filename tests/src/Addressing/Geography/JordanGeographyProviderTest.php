<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Jordan\JordanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Jordanian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'JO')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'MN',
        'name' => 'Maan',
        'label' => 'Maan',
    ]);

    app(JordanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ma\'an')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Jordanian state code to its area', function (): void {
    $mappings = app(JordanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_keys($mappings))->toBe(['AJ', 'AM', 'AQ', 'AT', 'AZ', 'BA', 'IR', 'JA', 'KA', 'MA', 'MD', 'MN']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(JordanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Jordanian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('JO');
    $country = AddressCountry::query()->where('iso2', 'JO')->firstOrFail();

    expect($result['seeded'])->toContain('JO')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
