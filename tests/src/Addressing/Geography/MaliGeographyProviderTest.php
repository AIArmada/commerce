<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Mali\MaliGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 11 Malian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ML')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BKO',
        'name' => 'Bamako (legacy)',
        'label' => 'Bamako (legacy)',
    ]);

    app(MaliGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bamako')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(11);
});

it('maps every Malian state code to its area', function (): void {
    $mappings = app(MaliGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(11)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BKO', '7', '1', '8', '2', '9', '5', '4', '3', '10', '6']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MaliGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Malian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ML');
    $country = AddressCountry::query()->where('iso2', 'ML')->firstOrFail();

    expect($result['seeded'])->toContain('ML')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(11);
});
