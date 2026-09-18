<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 24 Argentine states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'C',
        'name' => 'CABA',
        'label' => 'CABA',
    ]);

    app(ArgentinaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Autonomous City of Buenos Aires')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(24);
});

it('maps every Argentine state code to its area', function (): void {
    $mappings = app(ArgentinaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(24)
        ->and(array_keys($mappings))->toBe(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ArgentinaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Argentine tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AR');
    $country = AddressCountry::query()->where('iso2', 'AR')->firstOrFail();

    expect($result['seeded'])->toContain('AR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(23)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(24);
});
