<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\GuineaBissau\GuineaBissauGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Bissau-Guinean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BA',
        'name' => 'Bafatá (legacy)',
        'label' => 'Bafatá (legacy)',
    ]);

    app(GuineaBissauGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bafatá')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Bissau-Guinean state code to its area', function (): void {
    $mappings = app(GuineaBissauGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BA', 'BM', 'BS', 'BL', 'CA', 'GA', 'L', 'N', 'OI', 'QU', 'S', 'TO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuineaBissauGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bissau-Guinean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GW');
    $country = AddressCountry::query()->where('iso2', 'GW')->firstOrFail();

    expect($result['seeded'])->toContain('GW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_sector')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
