<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 78 Azerbaijani states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AZ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'ABS',
        'name' => 'Absheron (legacy)',
        'label' => 'Absheron (legacy)',
    ]);

    app(AzerbaijanGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Absheron')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(78);
});

it('maps every Azerbaijani state code to its area', function (): void {
    $mappings = app(AzerbaijanGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(78)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['ABS', 'AGM', 'AGS', 'AGC', 'AGA', 'AGU', 'AST', 'BAB', 'BA', 'BAL', 'BAR', 'BEY', 'BIL', 'DAS', 'FUZ', 'GA', 'GAD', 'QOB', 'GOR', 'GOY', 'GYG', 'HAC', 'IMI', 'ISM', 'CAB', 'CAL', 'CUL', 'KAL', 'KAN', 'XAC', 'XA', 'XIZ', 'XCI', 'KUR', 'LAC', 'LAN', 'LA', 'LER', 'XVD', 'MAS', 'MI', 'NA', 'NV', 'NX', 'NEF', 'OGU', 'ORD', 'QAB', 'QAX', 'QAZ', 'QBA', 'QBI', 'QUS', 'SAT', 'SAB', 'SAD', 'SAL', 'SMX', 'SBN', 'SAH', 'SA', 'SAK', 'SMI', 'SKR', 'SAR', 'SR', 'SUS', 'SIY', 'SM', 'TAR', 'TOV', 'UCA', 'YAR', 'YEV', 'YE', 'ZAN', 'ZAQ', 'ZAR']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AzerbaijanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Azerbaijani tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AZ');
    $country = AddressCountry::query()->where('iso2', 'AZ')->firstOrFail();

    expect($result['seeded'])->toContain('AZ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(66)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(11)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_republic')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(78);
});
