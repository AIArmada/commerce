<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Russia\RussiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 83 Russian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'RU')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'MOW',
        'name' => 'Moskva',
        'label' => 'Moskva',
    ]);

    app(RussiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Moscow')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(83);
});

it('maps every Russian state code to its area', function (): void {
    $mappings = app(RussiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(83)
        ->and(array_keys($mappings))->toBe(['AD', 'AL', 'ALT', 'AMU', 'ARK', 'AST', 'BA', 'BEL', 'BRY', 'BU', 'CE', 'CHE', 'CHU', 'CU', 'DA', 'IN', 'IRK', 'IVA', 'KAM', 'KB', 'KC', 'KDA', 'KEM', 'KGD', 'KGN', 'KHA', 'KHM', 'KIR', 'KK', 'KL', 'KLU', 'KO', 'KOS', 'KR', 'KRS', 'KYA', 'LEN', 'LIP', 'MAG', 'ME', 'MO', 'MOS', 'MOW', 'MUR', 'NEN', 'NGR', 'NIZ', 'NVS', 'OMS', 'ORE', 'ORL', 'PER', 'PNZ', 'PRI', 'PSK', 'ROS', 'RYA', 'SA', 'SAK', 'SAM', 'SAR', 'SE', 'SMO', 'SPE', 'STA', 'SVE', 'TA', 'TAM', 'TOM', 'TUL', 'TVE', 'TY', 'TYU', 'UD', 'ULY', 'VGG', 'VLA', 'VLG', 'VOR', 'YAN', 'YAR', 'YEV', 'ZAB']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(RussiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('subject')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Russian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('RU');
    $country = AddressCountry::query()->where('iso2', 'RU')->firstOrFail();

    expect($result['seeded'])->toContain('RU')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(83)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'republic')->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'krai')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'oblast')->count())->toBe(46)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'okrug')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'federal_city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_oblast')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(83);
});
