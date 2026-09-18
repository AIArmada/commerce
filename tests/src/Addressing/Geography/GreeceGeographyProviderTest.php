<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 15 Greek states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '13',
        'name' => 'Achaea (legacy)',
        'label' => 'Achaea (legacy)',
    ]);

    app(GreeceGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Achaea')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15);
});

it('maps every Greek state code to its area', function (): void {
    $mappings = app(GreeceGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(15)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['13', 'I', 'H', 'B', 'M', 'A2', 'A', 'D', 'F', 'K', 'J', 'L', 'E', 'G', 'C']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GreeceGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('administrative_region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Greek tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GR');
    $country = AddressCountry::query()->where('iso2', 'GR')->firstOrFail();

    expect($result['seeded'])->toContain('GR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'administrative_region')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'regional_unit')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);
});
