<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Israel\IsraelGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Israeli states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'M',
        'name' => 'Central (legacy)',
        'label' => 'Central (legacy)',
    ]);

    app(IsraelGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Israeli state code to its area', function (): void {
    $mappings = app(IsraelGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['M', 'HA', 'JM', 'Z', 'D', 'TA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IsraelGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Israeli tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IL');
    $country = AddressCountry::query()->where('iso2', 'IL')->firstOrFail();

    expect($result['seeded'])->toContain('IL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
