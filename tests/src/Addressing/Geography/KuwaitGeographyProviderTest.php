<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Kuwait\KuwaitGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 6 Kuwaiti states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'KW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'MU',
        'name' => 'Mubarak',
        'label' => 'Mubarak',
    ]);

    app(KuwaitGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Mubarak Al-Kabeer')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6);
});

it('maps every Kuwaiti state code to its area', function (): void {
    $mappings = app(KuwaitGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(6)
        ->and(array_keys($mappings))->toBe(['AH', 'FA', 'HA', 'JA', 'KU', 'MU']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KuwaitGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kuwaiti tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('KW');
    $country = AddressCountry::query()->where('iso2', 'KW')->firstOrFail();

    expect($result['seeded'])->toContain('KW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});
