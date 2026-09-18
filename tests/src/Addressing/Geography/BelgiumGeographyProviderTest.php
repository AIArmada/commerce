<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Belgium\BelgiumGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 13 Belgian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'VAN',
        'name' => 'Antwerp (legacy)',
        'label' => 'Antwerp (legacy)',
    ]);

    app(BelgiumGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Antwerp')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13);
});

it('maps every Belgian state code to its area', function (): void {
    $mappings = app(BelgiumGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(13)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['VAN', 'BRU', 'VOV', 'VLG', 'VBR', 'WHT', 'WLG', 'VLI', 'WLX', 'WNA', 'WAL', 'WBR', 'VWV']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BelgiumGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Belgian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BE');
    $country = AddressCountry::query()->where('iso2', 'BE')->firstOrFail();

    expect($result['seeded'])->toContain('BE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});
