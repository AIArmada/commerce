<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Pakistani states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'JK',
        'name' => 'Kashmir',
        'label' => 'Kashmir',
    ]);

    app(PakistanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Azad Jammu and Kashmir')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Pakistani state code to its area', function (): void {
    $mappings = app(PakistanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_keys($mappings))->toBe(['BA', 'GB', 'IS', 'JK', 'KP', 'PB', 'SD']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PakistanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Pakistani tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PK');
    $country = AddressCountry::query()->where('iso2', 'PK')->firstOrFail();

    expect($result['seeded'])->toContain('PK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'territory')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);

    $area = AddressArea::query()->where('source_id', 'pk:territory:azad-jammu-and-kashmir')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Azad Kashmir')
        ->exists())->toBeTrue();
});
