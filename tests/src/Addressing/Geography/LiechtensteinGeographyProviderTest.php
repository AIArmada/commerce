<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Liechtenstein\LiechtensteinGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 11 Liechtensteiner states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LI')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Balzers (legacy)',
        'label' => 'Balzers (legacy)',
    ]);

    app(LiechtensteinGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Balzers')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11);
});

it('maps every Liechtensteiner state code to its area', function (): void {
    $mappings = app(LiechtensteinGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(11)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LiechtensteinGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('commune')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Liechtensteiner tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LI');
    $country = AddressCountry::query()->where('iso2', 'LI')->firstOrFail();

    expect($result['seeded'])->toContain('LI')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'commune')->count())->toBe(11)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});
