<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 30 Irish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CW',
        'name' => 'Carlow (legacy)',
        'label' => 'Carlow (legacy)',
    ]);

    app(IrelandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Carlow')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(30);
});

it('maps every Irish state code to its area', function (): void {
    $mappings = app(IrelandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(30)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['CW', 'CN', 'CE', 'C', 'CO', 'DL', 'D', 'G', 'KY', 'KE', 'KK', 'LS', 'L', 'LM', 'LK', 'LD', 'LH', 'MO', 'MH', 'MN', 'M', 'OY', 'RN', 'SO', 'TA', 'U', 'WD', 'WH', 'WX', 'WW']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IrelandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Irish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IE');
    $country = AddressCountry::query()->where('iso2', 'IE')->firstOrFail();

    expect($result['seeded'])->toContain('IE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(30)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(26)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(30);
});
