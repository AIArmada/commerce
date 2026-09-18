<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Rwanda\RwandaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 5 Rwandan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'RW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '02',
        'name' => 'Eastern (legacy)',
        'label' => 'Eastern (legacy)',
    ]);

    app(RwandaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Eastern')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5);
});

it('maps every Rwandan state code to its area', function (): void {
    $mappings = app(RwandaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(5)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['02', '01', '03', '05', '04']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RwandaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Rwandan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('RW');
    $country = AddressCountry::query()->where('iso2', 'RW')->firstOrFail();

    expect($result['seeded'])->toContain('RW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});
