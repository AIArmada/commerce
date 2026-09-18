<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Afghanistan\AfghanistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 34 Afghan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AF')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'KAB',
        'name' => 'Kabul City',
        'label' => 'Kabul City',
    ]);

    app(AfghanistanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Kabul')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(34);
});

it('maps every Afghan state code to its area', function (): void {
    $mappings = app(AfghanistanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(34)
        ->and(array_keys($mappings))->toBe(['BAL', 'BAM', 'BDG', 'BDS', 'BGL', 'DAY', 'FRA', 'FYB', 'GHA', 'GHO', 'HEL', 'HER', 'JOW', 'KAB', 'KAN', 'KAP', 'KDZ', 'KHO', 'KNR', 'LAG', 'LOG', 'NAN', 'NIM', 'NUR', 'PAN', 'PAR', 'PIA', 'PKA', 'SAM', 'SAR', 'TAK', 'URU', 'WAR', 'ZAB']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AfghanistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Afghan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AF');
    $country = AddressCountry::query()->where('iso2', 'AF')->firstOrFail();

    expect($result['seeded'])->toContain('AF')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(34)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(34)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(34);
});
