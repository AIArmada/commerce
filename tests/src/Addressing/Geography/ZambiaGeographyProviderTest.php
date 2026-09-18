<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 10 Zambian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'ZM')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '02',
        'name' => 'Central (legacy)',
        'label' => 'Central (legacy)',
    ]);

    app(ZambiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10);
});

it('maps every Zambian state code to its area', function (): void {
    $mappings = app(ZambiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(10)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['02', '08', '03', '04', '09', '10', '05', '06', '07', '01']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ZambiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Zambian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('ZM');
    $country = AddressCountry::query()->where('iso2', 'ZM')->firstOrFail();

    expect($result['seeded'])->toContain('ZM')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});
