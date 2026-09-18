<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\NorthKorea\NorthKoreaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 13 North Korean states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'KP')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '04',
        'name' => 'Chagang (legacy)',
        'label' => 'Chagang (legacy)',
    ]);

    app(NorthKoreaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Chagang')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(13);
});

it('maps every North Korean state code to its area', function (): void {
    $mappings = app(NorthKoreaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(13)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['04', '15', '07', '14', '09', '06', '03', '01', '13', '10', '08', '05', '02']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NorthKoreaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the North Korean tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('KP');
    $country = AddressCountry::query()->where('iso2', 'KP')->firstOrFail();

    expect($result['seeded'])->toContain('KP')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'metropolitan_city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(13);
});
