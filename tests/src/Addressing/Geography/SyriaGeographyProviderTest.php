<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Syria\SyriaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Syrian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'HA',
        'name' => 'Al-Hasakah (legacy)',
        'label' => 'Al-Hasakah (legacy)',
    ]);

    app(SyriaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Al-Hasakah')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14);
});

it('maps every Syrian state code to its area', function (): void {
    $mappings = app(SyriaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['HA', 'RA', 'HL', 'SU', 'DI', 'DR', 'DY', 'HM', 'HI', 'ID', 'LA', 'QU', 'RD', 'TA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SyriaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Syrian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SY');
    $country = AddressCountry::query()->where('iso2', 'SY')->firstOrFail();

    expect($result['seeded'])->toContain('SY')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
