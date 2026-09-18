<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Palestine\PalestineGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 16 Palestinian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PS')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BTH',
        'name' => 'Bethlehem (legacy)',
        'label' => 'Bethlehem (legacy)',
    ]);

    app(PalestineGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bethlehem')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16);
});

it('maps every Palestinian state code to its area', function (): void {
    $mappings = app(PalestineGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(16)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BTH', 'DEB', 'GZA', 'HBN', 'JEN', 'JRH', 'JEM', 'KYS', 'NBS', 'NGZ', 'QQA', 'RFH', 'RBH', 'SLT', 'TBS', 'TKM']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PalestineGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Palestinian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PS');
    $country = AddressCountry::query()->where('iso2', 'PS')->firstOrFail();

    expect($result['seeded'])->toContain('PS')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});
