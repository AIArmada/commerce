<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Serbia\SerbiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 32 Serbian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'RS')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '00',
        'name' => 'Belgrade (legacy)',
        'label' => 'Belgrade (legacy)',
    ]);

    app(SerbiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Belgrade')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(32);
});

it('maps every Serbian state code to its area', function (): void {
    $mappings = app(SerbiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(32)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['00', '14', '11', '02', '23', '09', '25', 'KM', '29', '28', '08', '17', '20', '01', '03', '24', '26', '22', '10', '13', '27', '19', '18', '06', '04', '07', '12', '21', 'VO', '05', '15', '16']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SerbiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Serbian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('RS');
    $country = AddressCountry::query()->where('iso2', 'RS')->firstOrFail();

    expect($result['seeded'])->toContain('RS')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(32)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(29)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(32);
});
