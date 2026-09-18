<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 47 Japanese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'JP')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '13',
        'name' => 'Tokio',
        'label' => 'Tokio',
    ]);

    app(JapanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Tokyo')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(47);
});

it('maps every Japanese state code to its area', function (): void {
    $mappings = app(JapanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(47)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(JapanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('prefecture')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Japanese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('JP');
    $country = AddressCountry::query()->where('iso2', 'JP')->firstOrFail();

    expect($result['seeded'])->toContain('JP')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(47)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(47)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(47);
});
