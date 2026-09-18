<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Geography\Cambodia\CambodiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('seeds all 25 Cambodian states', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'KH')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '18',
        'name' => 'Sihanoukville',
        'label' => 'Sihanoukville',
    ]);

    app(CambodiaGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Preah Sihanouk')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(25);
});

it('maps every Cambodian state code to its area', function (): void {
    $mappings = app(CambodiaGeographyProvider::class)->stateAreaMappings();

    expect($mappings)->toHaveCount(25)
        ->and(array_map('strval', array_keys($mappings)))->toBe(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '25']);
});

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CambodiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cambodian tree with state links', function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    $result = app(SeedCountryGeographiesAction::class)->execute('KH');
    $country = AddressCountry::query()->where('iso2', 'KH')->firstOrFail();

    expect($result['seeded'])->toContain('KH')
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(25)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(24)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(25);

    $area = AddressArea::query()->where('source_id', 'kh:province:preah-sihanouk')->firstOrFail();

    expect(AddressAreaName::query()
        ->where('address_area_id', $area->getKey())
        ->where('name', 'Sihanoukville')
        ->exists())->toBeTrue();
});
