<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Belarus\BelarusGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Belarusian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'BR',
        'name' => 'Brest (legacy)',
        'label' => 'Brest (legacy)',
    ]);

    app(BelarusGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Brest')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Belarusian state code to its area', function (): void {
    $mappings = app(BelarusGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['BR', 'HO', 'HR', 'MI', 'HM', 'MA', 'VI']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BelarusGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('oblast')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Belarusian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BY');
    $country = AddressCountry::query()->where('iso2', 'BY')->firstOrFail();

    expect($result['seeded'])->toContain('BY')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'oblast')->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});
