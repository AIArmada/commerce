<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Oman\OmanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 11 Omani states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'OM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'MA',
        'name' => 'Musc',
        'label' => 'Musc',
    ]);

    app(OmanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Muscat')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11);
});

it('maps every Omani state code to its area', function (): void {
    $mappings = app(OmanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(11)
        ->and(array_keys($mappings))->toBe(['BJ', 'BS', 'BU', 'DA', 'MA', 'MU', 'SJ', 'SS', 'WU', 'ZA', 'ZU']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(OmanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Omani tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('OM');
    $country = AddressCountry::query()->where('iso2', 'OM')->firstOrFail();

    expect($result['seeded'])->toContain('OM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(11)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});
