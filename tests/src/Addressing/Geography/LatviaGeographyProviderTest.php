<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 43 Latvian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LV')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '011',
        'name' => 'Ādaži (legacy)',
        'label' => 'Ādaži (legacy)',
    ]);

    app(LatviaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ādaži')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(43);
});

it('maps every Latvian state code to its area', function (): void {
    $mappings = app(LatviaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(43)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['011', '002', '007', '111', '015', '016', '022', 'DGV', '112', '026', '033', '042', '041', 'JEL', 'JUR', '052', '047', '050', 'LPX', '054', '056', '058', '059', '062', '067', '068', '073', 'REZ', '077', 'RIX', '080', '087', '088', '089', '091', '094', '097', '099', '101', '113', '102', 'VEN', '106']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LatviaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Latvian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LV');
    $country = AddressCountry::query()->where('iso2', 'LV')->firstOrFail();

    expect($result['seeded'])->toContain('LV')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(43)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(36)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'state_city')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(43);
});
