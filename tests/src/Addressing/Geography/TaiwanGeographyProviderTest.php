<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 22 Taiwanese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'TPE',
        'name' => 'Taipei City',
        'label' => 'Taipei City',
    ]);

    app(TaiwanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Taipei')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22);
});

it('maps every Taiwanese state code to its area', function (): void {
    $mappings = app(TaiwanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(22)
        ->and(array_keys($mappings))->toBe(['CHA', 'CYI', 'CYQ', 'HSQ', 'HSZ', 'HUA', 'ILA', 'KEE', 'KHH', 'KIN', 'LIE', 'MIA', 'NAN', 'NWT', 'PEN', 'PIF', 'TAO', 'TNN', 'TPE', 'TTT', 'TXG', 'YUN']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TaiwanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('division')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Taiwanese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TW');
    $country = AddressCountry::query()->where('iso2', 'TW')->firstOrFail();

    expect($result['seeded'])->toContain('TW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_municipality')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});
