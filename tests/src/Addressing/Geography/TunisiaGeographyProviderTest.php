<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Tunisia\TunisiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 24 Tunisian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '12',
        'name' => 'Ariana (legacy)',
        'label' => 'Ariana (legacy)',
    ]);

    app(TunisiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ariana')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(24);
});

it('maps every Tunisian state code to its area', function (): void {
    $mappings = app(TunisiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(24)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['12', '31', '13', '23', '81', '71', '32', '41', '42', '73', '33', '53', '14', '82', '52', '21', '61', '43', '34', '51', '83', '72', '11', '22']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TunisiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Tunisian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TN');
    $country = AddressCountry::query()->where('iso2', 'TN')->firstOrFail();

    expect($result['seeded'])->toContain('TN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(24)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(24);
});
