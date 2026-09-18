<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\HongKong\HongKongGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 18 Hong Kong states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'HK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'HCW',
        'name' => 'Central and Western (legacy)',
        'label' => 'Central and Western (legacy)',
    ]);

    app(HongKongGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central and Western')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18);
});

it('maps every Hong Kong state code to its area', function (): void {
    $mappings = app(HongKongGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(18)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['HCW', 'HEA', 'NIS', 'KKC', 'NKT', 'KKT', 'NNO', 'NSK', 'NST', 'KSS', 'HSO', 'NTP', 'NTW', 'NTM', 'HWC', 'KWT', 'KYT', 'NYL']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HongKongGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Hong Kong tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('HK');
    $country = AddressCountry::query()->where('iso2', 'HK')->firstOrFail();

    expect($result['seeded'])->toContain('HK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});
