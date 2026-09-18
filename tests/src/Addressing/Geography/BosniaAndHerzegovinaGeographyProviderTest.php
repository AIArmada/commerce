<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\BosniaAndHerzegovina\BosniaAndHerzegovinaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 3 Bosnian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BA')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BRC',
        'name' => 'Brčko (legacy)',
        'label' => 'Brčko (legacy)',
    ]);

    app(BosniaAndHerzegovinaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Brčko')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3);
});

it('maps every Bosnian state code to its area', function (): void {
    $mappings = app(BosniaAndHerzegovinaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(3)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BRC', 'BIH', 'SRP']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BosniaAndHerzegovinaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('entity')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Bosnian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BA');
    $country = AddressCountry::query()->where('iso2', 'BA')->firstOrFail();

    expect($result['seeded'])->toContain('BA')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'entity')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});
