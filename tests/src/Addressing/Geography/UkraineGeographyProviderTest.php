<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Ukraine\UkraineGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 27 Ukrainian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'UA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '30',
        'name' => 'Kiev',
        'label' => 'Kiev',
    ]);

    app(UkraineGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Kyiv')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(27);
});

it('maps every Ukrainian state code to its area', function (): void {
    $mappings = app(UkraineGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(27)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['05', '07', '09', '12', '14', '18', '21', '23', '26', '30', '32', '35', '40', '43', '46', '48', '51', '53', '56', '59', '61', '63', '65', '68', '71', '74', '77']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(UkraineGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('oblast')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Ukrainian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('UA');
    $country = AddressCountry::query()->where('iso2', 'UA')->firstOrFail();

    expect($result['seeded'])->toContain('UA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'oblast')->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'republic')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(27);
});
