<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Iraq\IraqGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 19 Iraqi states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'IQ')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'QA',
        'name' => 'Qadisiyyah',
        'label' => 'Qadisiyyah',
    ]);

    State::query()->create([
        'country_id' => $country->id,
        'code' => 'KR',
        'name' => 'Southern Nations, Nationalities, and Peoples',
        'label' => 'Southern Nations, Nationalities, and Peoples',
    ]);

    app(IraqGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Al-Qadisiyyah')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(19)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'KR')->exists())->toBeFalse();
});

it('maps every Iraqi state code to its area', function (): void {
    $mappings = app(IraqGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(19)
        ->and(array_keys($mappings))->toBe(['AN', 'AR', 'BA', 'BB', 'BG', 'DA', 'DI', 'DQ', 'HL', 'KA', 'KI', 'MA', 'MU', 'NA', 'NI', 'QA', 'SD', 'SU', 'WA']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(IraqGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Iraqi tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('IQ');
    $country = AddressCountry::query()->where('iso2', 'IQ')->firstOrFail();

    expect($result['seeded'])->toContain('IQ')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(19)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(19)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(19);
});
