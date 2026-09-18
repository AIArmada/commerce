<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Madagascar\MadagascarGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Malagasy states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'T',
        'name' => 'Tana',
        'label' => 'Tana',
    ]);

    app(MadagascarGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Antananarivo')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Malagasy state code to its area', function (): void {
    $mappings = app(MadagascarGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_keys($mappings))->toBe(['A', 'D', 'F', 'M', 'T', 'U']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MadagascarGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Malagasy tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MG');
    $country = AddressCountry::query()->where('iso2', 'MG')->firstOrFail();

    expect($result['seeded'])->toContain('MG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
