<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Congolese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'CG')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '11',
        'name' => 'Bouenza (legacy)',
        'label' => 'Bouenza (legacy)',
    ]);

    app(CongoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bouenza')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Congolese state code to its area', function (): void {
    $mappings = app(CongoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['11', 'BZV', '8', '15', '5', '2', '7', '9', '14', '16', '12', '13']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CongoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Congolese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('CG');
    $country = AddressCountry::query()->where('iso2', 'CG')->firstOrFail();

    expect($result['seeded'])->toContain('CG')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
