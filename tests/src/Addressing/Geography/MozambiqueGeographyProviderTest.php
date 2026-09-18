<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Mozambique\MozambiqueGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 11 Mozambican states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'MPM',
        'name' => 'Maputo',
        'label' => 'Maputo',
    ]);

    app(MozambiqueGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Maputo City')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11);
});

it('maps every Mozambican state code to its area', function (): void {
    $mappings = app(MozambiqueGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(11)
        ->and(array_keys($mappings))->toBe(['A', 'B', 'G', 'I', 'L', 'MPM', 'N', 'P', 'Q', 'S', 'T']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MozambiqueGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mozambican tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MZ');
    $country = AddressCountry::query()->where('iso2', 'MZ')->firstOrFail();

    expect($result['seeded'])->toContain('MZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});
