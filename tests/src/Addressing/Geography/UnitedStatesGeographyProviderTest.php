<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 56 American states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'US')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CA',
        'name' => 'Calif',
        'label' => 'Calif',
    ]);

    app(UnitedStatesGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('California')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(56);
});

it('maps every American state code to its area', function (): void {
    $mappings = app(UnitedStatesGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(56)
        ->and(array_keys($mappings))->toBe(['AK', 'AL', 'AR', 'AS', 'AZ', 'CA', 'CO', 'CT', 'DC', 'DE', 'FL', 'GA', 'GU', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS', 'KY', 'LA', 'MA', 'MD', 'ME', 'MI', 'MN', 'MO', 'MP', 'MS', 'MT', 'NC', 'ND', 'NE', 'NH', 'NJ', 'NM', 'NV', 'NY', 'OH', 'OK', 'OR', 'PA', 'PR', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VA', 'VI', 'VT', 'WA', 'WI', 'WV', 'WY']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UnitedStatesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('state')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the American tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('US');
    $country = AddressCountry::query()->where('iso2', 'US')->firstOrFail();

    expect($result['seeded'])->toContain('US')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(56)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state')->count())->toBe(50)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territory')->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(56);
});
