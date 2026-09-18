<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\UnitedArabEmirates\UnitedArabEmiratesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Emirati states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'DU',
        'name' => 'Dub',
        'label' => 'Dub',
    ]);

    app(UnitedArabEmiratesGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Dubai')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Emirati state code to its area', function (): void {
    $mappings = app(UnitedArabEmiratesGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_keys($mappings))->toBe(['AJ', 'AZ', 'DU', 'FU', 'RK', 'SH', 'UQ']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UnitedArabEmiratesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('emirate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Emirati tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AE');
    $country = AddressCountry::query()->where('iso2', 'AE')->firstOrFail();

    expect($result['seeded'])->toContain('AE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'emirate')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});
