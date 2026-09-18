<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\UnitedKingdom\UnitedKingdomGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 4 British states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GB')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'ENG',
        'name' => 'Eng',
        'label' => 'Eng',
    ]);

    app(UnitedKingdomGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('England')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4);
});

it('maps every British state code to its area', function (): void {
    $mappings = app(UnitedKingdomGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(4)
        ->and(array_keys($mappings))->toBe(['ENG', 'NIR', 'SCT', 'WLS']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UnitedKingdomGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('nation')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the British tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GB');
    $country = AddressCountry::query()->where('iso2', 'GB')->firstOrFail();

    expect($result['seeded'])->toContain('GB')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'nation')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);
});
