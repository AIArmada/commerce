<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 16 Botswanan states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BW')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => 'CE',
        'name' => 'Central (legacy)',
        'label' => 'Central (legacy)',
    ]);

    app(BotswanaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16);
});

it('maps every Botswanan state code to its area', function (): void {
    $mappings = app(BotswanaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(16)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['CE', 'CH', 'FR', 'GA', 'GH', 'JW', 'KG', 'KL', 'KW', 'LO', 'NE', 'NW', 'SP', 'SE', 'SO', 'ST']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BotswanaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Botswanan tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('BW');
    $country = AddressCountry::query()->where('iso2', 'BW')->firstOrFail();

    expect($result['seeded'])->toContain('BW')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'town')->count())->toBe(4)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});
