<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Thailand\ThailandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 78 Thai states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'TH')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '10',
        'name' => 'Krung Thep',
        'label' => 'Krung Thep',
    ]);

    app(ThailandGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bangkok')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(78);
});

it('maps every Thai state code to its area', function (): void {
    $mappings = app(ThailandGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(78)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '60', '61', '62', '63', '64', '65', '66', '67', '70', '71', '72', '73', '74', '75', '76', '77', '80', '81', '82', '83', '84', '85', '86', '90', '91', '92', '93', '94', '95', '96', 'S']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ThailandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Thai tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('TH');
    $country = AddressCountry::query()->where('iso2', 'TH')->firstOrFail();

    expect($result['seeded'])->toContain('TH')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(78)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'metropolitan_administration')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(76)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(78);
});
