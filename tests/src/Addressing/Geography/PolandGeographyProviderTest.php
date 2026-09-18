<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 16 Polish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PL')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '14',
        'name' => 'Mazowsze',
        'label' => 'Mazowsze',
    ]);

    app(PolandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Mazovia')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16);
});

it('maps every Polish state code to its area', function (): void {
    $mappings = app(PolandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(16)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['02', '04', '06', '08', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PolandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('voivodeship')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Polish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PL');
    $country = AddressCountry::query()->where('iso2', 'PL')->firstOrFail();

    expect($result['seeded'])->toContain('PL')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'voivodeship')->count())->toBe(16)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});
