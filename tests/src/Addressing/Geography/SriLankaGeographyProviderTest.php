<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\SriLanka\SriLankaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 34 Sri Lankan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'LK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '52',
        'name' => 'Ampara (legacy)',
        'label' => 'Ampara (legacy)',
    ]);

    app(SriLankaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ampara')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(34);
});

it('maps every Sri Lankan state code to its area', function (): void {
    $mappings = app(SriLankaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(34)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['52', '71', '81', '51', '2', '11', '5', '31', '12', '33', '41', '13', '21', '92', '42', '61', '43', '22', '32', '82', '45', '7', '6', '4', '23', '72', '62', '91', '9', '3', '53', '8', '44', '1']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SriLankaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sri Lankan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('LK');
    $country = AddressCountry::query()->where('iso2', 'LK')->firstOrFail();

    expect($result['seeded'])->toContain('LK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(34)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(25)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(34);
});
