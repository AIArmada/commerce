<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Malawi\MalawiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 31 Malawian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BA',
        'name' => 'Balaka (legacy)',
        'label' => 'Balaka (legacy)',
    ]);

    app(MalawiGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Balaka')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(31);
});

it('maps every Malawian state code to its area', function (): void {
    $mappings = app(MalawiGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(31)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BA', 'BL', 'C', 'CK', 'CR', 'CT', 'DE', 'DO', 'KR', 'KS', 'LK', 'LI', 'MH', 'MG', 'MC', 'MU', 'MW', 'MZ', 'NE', 'NB', 'NK', 'N', 'NS', 'NU', 'NI', 'PH', 'RU', 'SA', 'S', 'TH', 'ZO']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MalawiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Malawian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MW');
    $country = AddressCountry::query()->where('iso2', 'MW')->firstOrFail();

    expect($result['seeded'])->toContain('MW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(31)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(28)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(31);
});
