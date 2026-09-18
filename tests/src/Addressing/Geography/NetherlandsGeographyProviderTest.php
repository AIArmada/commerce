<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Dutch states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'NL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'NH',
        'name' => 'Holland',
        'label' => 'Holland',
    ]);

    app(NetherlandsGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Noord-Holland')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Dutch state code to its area', function (): void {
    $mappings = app(NetherlandsGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_keys($mappings))->toBe(['DR', 'FL', 'FR', 'GE', 'GR', 'LI', 'NB', 'NH', 'OV', 'UT', 'ZE', 'ZH']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NetherlandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Dutch tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('NL');
    $country = AddressCountry::query()->where('iso2', 'NL')->firstOrFail();

    expect($result['seeded'])->toContain('NL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
