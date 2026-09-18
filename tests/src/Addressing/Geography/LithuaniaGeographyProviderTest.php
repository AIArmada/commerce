<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 70 Lithuanian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LT')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Akmenė (legacy)',
        'label' => 'Akmenė (legacy)',
    ]);

    app(LithuaniaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Akmenė')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(70);
});

it('maps every Lithuanian state code to its area', function (): void {
    $mappings = app(LithuaniaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(70)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '03', '02', 'AL', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', 'KU', '16', '15', '17', '18', '19', 'KL', '21', '20', '22', '23', '24', '25', 'MR', '26', '27', '28', '29', '30', '31', '32', '33', 'PN', '34', '35', '36', '37', '38', '39', '40', '41', '42', 'SA', '43', '44', '45', '46', '47', '48', '49', 'TA', '50', '51', 'TE', '52', '53', '54', 'UT', '55', '56', '58', '57', 'VL', '59', '60']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LithuaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Lithuanian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LT');
    $country = AddressCountry::query()->where('iso2', 'LT')->firstOrFail();

    expect($result['seeded'])->toContain('LT')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(70)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district_municipality')->count())->toBe(49)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city_municipality')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(70);
});
