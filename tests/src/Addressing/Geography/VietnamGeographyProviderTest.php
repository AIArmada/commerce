<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Vietnam\VietnamGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 34 Vietnamese states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'VN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '26',
        'name' => 'Thua Thien-Hue',
        'label' => 'Thua Thien-Hue',
    ]);

    app(VietnamGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Huế')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(34);
});

it('maps every Vietnamese state code to its area', function (): void {
    $mappings = app(VietnamGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(34)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '04', '05', '07', '09', '13', '18', '21', '22', '23', '25', '26', '29', '30', '33', '34', '35', '37', '39', '44', '45', '49', '56', '59', '66', '68', '69', '71', 'CT', 'DN', 'HN', 'HP', 'SG']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(VietnamGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Vietnamese tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('VN');
    $country = AddressCountry::query()->where('iso2', 'VN')->firstOrFail();

    expect($result['seeded'])->toContain('VN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(34)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(28)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(34);
});
