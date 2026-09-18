<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Timorese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'DI',
        'name' => 'Díli',
        'label' => 'Díli',
    ]);

    app(TimorLesteGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Dili')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'AT')->where('name', 'Atauro')->exists())->toBeTrue();
});

it('maps every Timorese state code to its area', function (): void {
    $mappings = app(TimorLesteGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AL', 'AN', 'BA', 'BO', 'CO', 'DI', 'ER', 'LA', 'LI', 'MT', 'MF', 'OE', 'VI', 'AT']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TimorLesteGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Timorese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TL');
    $country = AddressCountry::query()->where('iso2', 'TL')->firstOrFail();

    expect($result['seeded'])->toContain('TL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_administrative_region')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
