<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\IvoryCoast\IvoryCoastGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 14 Ivorian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CI')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AB',
        'name' => 'Abidjan (legacy)',
        'label' => 'Abidjan (legacy)',
    ]);

    app(IvoryCoastGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Abidjan')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14);
});

it('maps every Ivorian state code to its area', function (): void {
    $mappings = app(IvoryCoastGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(14)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AB', 'BS', 'CM', 'DN', 'GD', 'LC', 'LG', 'MG', 'SM', 'SV', 'VB', 'WR', 'YM', 'ZZ']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IvoryCoastGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ivorian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CI');
    $country = AddressCountry::query()->where('iso2', 'CI')->firstOrFail();

    expect($result['seeded'])->toContain('CI')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_district')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});
