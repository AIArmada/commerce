<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Peru\PeruGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 26 Peruvian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'PE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'HUC',
        'name' => 'Huanuco',
        'label' => 'Huanuco',
    ]);

    app(PeruGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Huánuco')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(26);
});

it('maps every Peruvian state code to its area', function (): void {
    $mappings = app(PeruGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(26)
        ->and(array_keys($mappings))->toBe(['AMA', 'ANC', 'APU', 'ARE', 'AYA', 'CAJ', 'CAL', 'CUS', 'HUC', 'HUV', 'ICA', 'JUN', 'LAL', 'LAM', 'LIM', 'LMA', 'LOR', 'MDD', 'MOQ', 'PAS', 'PIU', 'PUN', 'SAM', 'TAC', 'TUM', 'UCA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(PeruGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Peruvian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('PE');
    $country = AddressCountry::query()->where('iso2', 'PE')->firstOrFail();

    expect($result['seeded'])->toContain('PE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(26);
});
