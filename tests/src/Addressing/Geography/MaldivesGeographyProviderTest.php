<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Maldives\MaldivesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 21 Maldivian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MV')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Addu (legacy)',
        'label' => 'Addu (legacy)',
    ]);

    app(MaldivesGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Addu')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(21);
});

it('maps every Maldivian state code to its area', function (): void {
    $mappings = app(MaldivesGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(21)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '00', '20', '17', '14', '27', '28', '29', '07', '23', '26', '05', '03', 'MLE', '12', '25', '13', '24', '08', '04']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MaldivesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('atoll')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Maldivian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MV');
    $country = AddressCountry::query()->where('iso2', 'MV')->firstOrFail();

    expect($result['seeded'])->toContain('MV')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'atoll')->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(21);
});
