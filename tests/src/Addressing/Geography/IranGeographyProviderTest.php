<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Iran\IranGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 31 Iranian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '30',
        'name' => 'Alborz (legacy)',
        'label' => 'Alborz (legacy)',
    ]);

    app(IranGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Alborz')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(31);
});

it('maps every Iranian state code to its area', function (): void {
    $mappings = app(IranGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(31)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['30', '24', '18', '14', '03', '07', '01', '27', '13', '22', '16', '10', '08', '05', '06', '17', '12', '15', '00', '02', '28', '26', '25', '09', '20', '11', '29', '23', '04', '21', '19']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IranGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Iranian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IR');
    $country = AddressCountry::query()->where('iso2', 'IR')->firstOrFail();

    expect($result['seeded'])->toContain('IR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(31)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(31)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(31);
});
