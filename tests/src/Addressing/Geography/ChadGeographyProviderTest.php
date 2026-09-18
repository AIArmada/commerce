<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Chad\ChadGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 23 Chadian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BG',
        'name' => 'Bahr el Gazel (legacy)',
        'label' => 'Bahr el Gazel (legacy)',
    ]);

    app(ChadGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bahr el Gazel')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(23);
});

it('maps every Chadian state code to its area', function (): void {
    $mappings = app(ChadGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(23)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BG', 'BA', 'BO', 'CB', 'EE', 'EO', 'GR', 'HL', 'KA', 'LC', 'LO', 'LR', 'MA', 'ME', 'MO', 'MC', 'ND', 'OD', 'SA', 'SI', 'TA', 'TI', 'WF']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ChadGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Chadian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TD');
    $country = AddressCountry::query()->where('iso2', 'TD')->firstOrFail();

    expect($result['seeded'])->toContain('TD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(23)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(23)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(23);
});
