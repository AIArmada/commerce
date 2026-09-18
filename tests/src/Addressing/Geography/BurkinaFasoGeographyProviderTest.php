<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 58 Burkinabe states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BF')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BAL',
        'name' => 'Balé (legacy)',
        'label' => 'Balé (legacy)',
    ]);

    app(BurkinaFasoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Balé')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(58);
});

it('maps every Burkinabe state code to its area', function (): void {
    $mappings = app(BurkinaFasoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(58)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BAL', 'BAM', 'BAN', 'BAZ', '01', 'BGR', 'BLG', 'BLK', '02', '03', '04', '05', '06', '07', 'COM', '08', 'GAN', 'GNA', 'GOU', '09', 'HOU', 'IOB', 'KAD', 'KEN', 'KMD', 'KMP', 'KOS', 'KOP', 'KOT', 'KOW', 'LER', 'LOR', 'MOU', 'NAO', 'NAM', 'NAY', '10', 'NOU', 'OUB', 'OUD', 'PAS', '11', 'PON', '12', 'SNG', 'SMT', 'SEN', 'SIS', 'SOM', 'SOR', '13', 'TAP', 'TUI', 'YAG', 'YAT', 'ZIR', 'ZON', 'ZOU']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BurkinaFasoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Burkinabe tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BF');
    $country = AddressCountry::query()->where('iso2', 'BF')->firstOrFail();

    expect($result['seeded'])->toContain('BF')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(58)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(45)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(58);
});
