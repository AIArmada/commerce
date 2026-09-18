<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 26 Swiss states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CH')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AG',
        'name' => 'Aargau (legacy)',
        'label' => 'Aargau (legacy)',
    ]);

    app(SwitzerlandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Aargau')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(26);
});

it('maps every Swiss state code to its area', function (): void {
    $mappings = app(SwitzerlandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(26)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AG', 'AR', 'AI', 'BL', 'BS', 'BE', 'FR', 'GE', 'GL', 'GR', 'JU', 'LU', 'NE', 'NW', 'OW', 'SH', 'SZ', 'SO', 'SG', 'TG', 'TI', 'UR', 'VS', 'VD', 'ZG', 'ZH']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SwitzerlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('canton')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Swiss tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CH');
    $country = AddressCountry::query()->where('iso2', 'CH')->firstOrFail();

    expect($result['seeded'])->toContain('CH')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'canton')->count())->toBe(26)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(26);
});
