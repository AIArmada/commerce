<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Croatia\CroatiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 20 Croatian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'HR')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '07',
        'name' => 'Bjelovar-Bilogora (legacy)',
        'label' => 'Bjelovar-Bilogora (legacy)',
    ]);

    app(CroatiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Bjelovar-Bilogora')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20);
});

it('maps every Croatian state code to its area', function (): void {
    $mappings = app(CroatiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(20)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['07', '12', '19', '18', '04', '06', '02', '09', '20', '14', '11', '08', '15', '03', '17', '05', '10', '16', '13', '01']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CroatiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Croatian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('HR');
    $country = AddressCountry::query()->where('iso2', 'HR')->firstOrFail();

    expect($result['seeded'])->toContain('HR')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(20)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});
