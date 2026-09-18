<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 12 Georgian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'GE')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'AB',
        'name' => 'Abkhazia (legacy)',
        'label' => 'Abkhazia (legacy)',
    ]);

    app(GeorgiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Abkhazia')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12);
});

it('maps every Georgian state code to its area', function (): void {
    $mappings = app(GeorgiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(12)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['AB', 'AJ', 'GU', 'IM', 'KA', 'KK', 'MM', 'RL', 'SZ', 'SJ', 'SK', 'TB']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GeorgiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Georgian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('GE');
    $country = AddressCountry::query()->where('iso2', 'GE')->firstOrFail();

    expect($result['seeded'])->toContain('GE')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'autonomous_republic')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});
