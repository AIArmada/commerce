<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 41 Guinean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BE',
        'name' => 'Beyla (legacy)',
        'label' => 'Beyla (legacy)',
    ]);

    app(GuineaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Beyla')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(41);
});

it('maps every Guinean state code to its area', function (): void {
    $mappings = app(GuineaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(41)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BE', 'BF', 'B', 'BK', 'C', 'CO', 'DB', 'DL', 'DI', 'DU', 'F', 'FA', 'FO', 'FR', 'GA', 'GU', 'KA', 'K', 'KE', 'D', 'KD', 'KS', 'KB', 'KN', 'KO', 'LA', 'L', 'LE', 'LO', 'MC', 'ML', 'M', 'MM', 'MD', 'N', 'NZ', 'PI', 'SI', 'TE', 'TO', 'YO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuineaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('administrative_region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guinean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GN');
    $country = AddressCountry::query()->where('iso2', 'GN')->firstOrFail();

    expect($result['seeded'])->toContain('GN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(41)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'administrative_region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(33)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(41);
});
