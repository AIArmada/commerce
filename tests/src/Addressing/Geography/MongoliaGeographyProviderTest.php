<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Mongolia\MongoliaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 22 Mongolian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'MN')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '073',
        'name' => 'Arkhangai (legacy)',
        'label' => 'Arkhangai (legacy)',
    ]);

    app(MongoliaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Arkhangai')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(22);
});

it('maps every Mongolian state code to its area', function (): void {
    $mappings = app(MongoliaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(22)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['073', '071', '069', '067', '037', '061', '063', '059', '065', '064', '039', '043', '041', '053', '035', '055', '049', '051', '047', '001', '046', '057']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(MongoliaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Mongolian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('MN');
    $country = AddressCountry::query()->where('iso2', 'MN')->firstOrFail();

    expect($result['seeded'])->toContain('MN')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(22)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(22);
});
