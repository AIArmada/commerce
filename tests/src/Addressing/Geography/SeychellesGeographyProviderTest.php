<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 27 Seychellois states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'SC')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '02',
        'name' => 'Anse Boileau (legacy)',
        'label' => 'Anse Boileau (legacy)',
    ]);

    app(SeychellesGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Anse Boileau')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(27);
});

it('maps every Seychellois state code to its area', function (): void {
    $mappings = app(SeychellesGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(27)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['02', '03', '05', '01', '04', '06', '07', '08', '09', '10', '11', '12', '13', '14', '26', '27', '15', '16', '24', '17', '18', '19', '20', '21', '25', '22', '23']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SeychellesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Seychellois tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('SC');
    $country = AddressCountry::query()->where('iso2', 'SC')->firstOrFail();

    expect($result['seeded'])->toContain('SC')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(27)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(27);
});
