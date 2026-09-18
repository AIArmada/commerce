<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Mauritania\MauritaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 15 Mauritanian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '07',
        'name' => 'Adrar (legacy)',
        'label' => 'Adrar (legacy)',
    ]);

    app(MauritaniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Adrar')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15);
});

it('maps every Mauritanian state code to its area', function (): void {
    $mappings = app(MauritaniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(15)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['07', '03', '05', '08', '04', '10', '01', '02', '12', '14', '13', '15', '09', '11', '06']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MauritaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mauritanian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MR');
    $country = AddressCountry::query()->where('iso2', 'MR')->firstOrFail();

    expect($result['seeded'])->toContain('MR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(15)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);
});
