<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 7 Andorran states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'AD')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '07',
        'name' => 'Andorra la Vella (legacy)',
        'label' => 'Andorra la Vella (legacy)',
    ]);

    app(AndorraGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Andorra la Vella')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7);
});

it('maps every Andorran state code to its area', function (): void {
    $mappings = app(AndorraGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(7)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['07', '02', '03', '08', '04', '05', '06']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(AndorraGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Andorran tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('AD');
    $country = AddressCountry::query()->where('iso2', 'AD')->firstOrFail();

    expect($result['seeded'])->toContain('AD')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});
