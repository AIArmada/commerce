<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Turkiye\TurkiyeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 81 Turkish states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '34',
        'name' => 'Istanbul',
        'label' => 'Istanbul',
    ]);

    app(TurkiyeGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('İstanbul')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(81);
});

it('maps every Turkish state code to its area', function (): void {
    $mappings = app(TurkiyeGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(81)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TurkiyeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Turkish tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TR');
    $country = AddressCountry::query()->where('iso2', 'TR')->firstOrFail();

    expect($result['seeded'])->toContain('TR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(81)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(81)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(81);
});
