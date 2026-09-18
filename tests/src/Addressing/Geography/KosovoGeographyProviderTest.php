<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Kosovo\KosovoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Kosovar states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'XK')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'XUF',
        'name' => 'Ferizaj (legacy)',
        'label' => 'Ferizaj (legacy)',
    ]);

    app(KosovoGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Ferizaj')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Kosovar state code to its area', function (): void {
    $mappings = app(KosovoGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['XUF', 'XDG', 'XGJ', 'XKM', 'PEJ', 'XPI', 'PRI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KosovoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kosovar tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('XK');
    $country = AddressCountry::query()->where('iso2', 'XK')->firstOrFail();

    expect($result['seeded'])->toContain('XK')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});
